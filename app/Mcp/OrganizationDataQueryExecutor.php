<?php

namespace App\Mcp;

use App\Models\StockBalance;
use App\Support\Billing\OrganizationFeatureEntitlement;
use App\Support\Inventory\StockBalanceReportQuery;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Compiles the bounded organization-data contract into server-owned Eloquent
 * queries. Callers never provide table names, database columns, or SQL.
 */
final class OrganizationDataQueryExecutor
{
    private const int DEFAULT_LIMIT = 50;

    private const int MAX_LIMIT = 200;

    /** @var list<string> */
    private const array ROOT_KEYS = [
        'operation',
        'resource',
        'fields',
        'filters',
        'relations',
        'aggregates',
        'group_by',
        'sort',
        'page',
        'limit',
    ];

    /**
     * Create the organization-data query executor.
     */
    public function __construct(
        private readonly OrganizationDataCatalog $catalog,
        private readonly ReadOnlyOrganizationDataSession $session,
        private readonly StockBalanceReportQuery $stockBalanceReportQuery,
    ) {}

    /**
     * Validate, authorize, compile, execute, and serialize one read-only query.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(
        AiMcpExecutionIdentity $identity,
        array $input,
    ): array {
        $request = $this->validateRequest($input);
        $descriptor = $this->resource($request['resource']);

        $this->authorize($identity, $descriptor);

        $fields = $request['fields']
            ?? (
                $request['operation'] === 'list'
                    ? array_keys(
                        array_filter(
                            $descriptor['fields'],
                            static fn (mixed $field): bool => ! is_array($field)
                                || ($field['cost'] ?? false) !== true,
                        ),
                    )
                    : []
            );

        $relations = $request['relations'] ?? [];

        $this->validateSelection(
            $descriptor,
            $fields,
            $relations,
            $request,
        );

        if (
            $this->requiresCosts(
                $descriptor,
                $fields,
                $relations,
                $request,
            )
            && ! Gate::forUser($identity->user)->allows(
                'costs.view',
                $identity->organization,
            )
        ) {
            throw new AuthorizationException(
                'Cost and valuation fields require costs.view permission.',
            );
        }

        if ($request['operation'] === 'aggregate') {
            return $this->aggregate(
                $identity,
                $descriptor,
                $request,
            );
        }

        return $this->session->run(
            function () use (
                $identity,
                $descriptor,
                $request,
                $fields,
                $relations,
            ): array {
                $query = $this->query($identity, $descriptor);

                // The generic tool only loads explicitly requested relations.
                $query->setEagerLoads([]);

                $this->applyFilters(
                    $query,
                    $descriptor,
                    $request['filters'] ?? [],
                );

                $this->applySort(
                    $query,
                    $descriptor,
                    $request['sort'] ?? [],
                );

                $query->select(
                    $this->selectionColumns(
                        $descriptor,
                        $fields,
                        $relations,
                    ),
                );

                $this->loadRelations(
                    $query,
                    $descriptor,
                    $relations,
                );

                $limit = $request['limit'] ?? self::DEFAULT_LIMIT;
                $page = $request['page'] ?? 1;

                $rows = $query
                    ->forPage($page, $limit + 1)
                    ->get();

                $truncated = $rows->count() > $limit;

                return [
                    'rows' => $rows
                        ->take($limit)
                        ->map(
                            fn (Model $model): array => $this->serialize(
                                $model,
                                $descriptor,
                                $fields,
                                $relations,
                            ),
                        )
                        ->values()
                        ->all(),
                    'metadata' => [
                        'resource' => $request['resource'],
                        'page' => $page,
                        'limit' => $limit,
                        'returned_row_count' => min(
                            $rows->count(),
                            $limit,
                        ),
                        'truncated' => $truncated,
                    ],
                ];
            },
        );
    }

    /**
     * Validate the bounded top-level query envelope.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateRequest(array $input): array
    {
        $this->only($input, self::ROOT_KEYS, 'request');

        $operation = $input['operation'] ?? 'list';

        if (! in_array($operation, ['list', 'aggregate'], true)) {
            $this->invalid(
                'operation',
                'must be list or aggregate.',
            );
        }

        if (! is_string($input['resource'] ?? null)) {
            $this->invalid('resource', 'is required.');
        }

        foreach (['fields', 'relations', 'group_by'] as $key) {
            if (
                isset($input[$key])
                && (
                    ! is_array($input[$key])
                    || ! array_is_list($input[$key])
                )
            ) {
                $this->invalid($key, 'must be a list.');
            }
        }

        foreach (['filters', 'sort', 'aggregates'] as $key) {
            if (
                isset($input[$key])
                && (
                    ! is_array($input[$key])
                    || ! array_is_list($input[$key])
                )
            ) {
                $this->invalid($key, 'must be a list.');
            }
        }

        foreach (
            [
                'page' => 1000,
                'limit' => self::MAX_LIMIT,
            ] as $key => $maximum
        ) {
            if (
                isset($input[$key])
                && (
                    ! is_int($input[$key])
                    || $input[$key] < 1
                    || $input[$key] > $maximum
                )
            ) {
                $this->invalid(
                    $key,
                    "must be an integer between 1 and {$maximum}.",
                );
            }
        }

        if (
            count($input['fields'] ?? []) > 20
            || count($input['filters'] ?? []) > 8
            || count($input['relations'] ?? []) > 3
            || count($input['sort'] ?? []) > 3
            || count($input['aggregates'] ?? []) > 2
            || count($input['group_by'] ?? []) > 2
        ) {
            $this->invalid(
                'request',
                'exceeds the query complexity limit.',
            );
        }

        if (
            $operation === 'list'
            && (
                ($input['aggregates'] ?? []) !== []
                || ($input['group_by'] ?? []) !== []
            )
        ) {
            $this->invalid(
                'request',
                'list queries cannot contain aggregates or group_by.',
            );
        }

        return [
            'operation' => $operation,
            ...$input,
        ];
    }

    /**
     * Resolve one resource strictly from the server-owned catalog.
     *
     * @return array<string, mixed>
     */
    private function resource(string $resource): array
    {
        $resources = $this->catalog->resources();

        if (! isset($resources[$resource])) {
            $this->invalid(
                'resource',
                'is not supported.',
            );
        }

        return $resources[$resource];
    }

    /**
     * Enforce the resource permission and commercial feature boundary.
     *
     * @param  array<string, mixed>  $descriptor
     */
    private function authorize(
        AiMcpExecutionIdentity $identity,
        array $descriptor,
    ): void {
        foreach ($descriptor['permissions'] as $permission) {
            if (
                Gate::forUser($identity->user)->allows(
                    $permission->value,
                    $identity->organization,
                )
            ) {
                if (
                    ($descriptor['feature'] ?? null) === null
                    || OrganizationFeatureEntitlement::isGranted(
                        $identity->organization,
                        $descriptor['feature'],
                    )
                ) {
                    return;
                }

                throw new AuthorizationException(
                    'This resource is not included in the organization\'s current plan.',
                );
            }
        }

        throw new AuthorizationException(
            'You are not authorized to query this organization resource.',
        );
    }

    /**
     * Validate requested fields, relations, groups, and aggregates.
     *
     * @param  array<string, mixed>  $descriptor
     * @param  list<mixed>  $fields
     * @param  list<mixed>  $relations
     * @param  array<string, mixed>  $request
     */
    private function validateSelection(
        array $descriptor,
        array $fields,
        array $relations,
        array $request,
    ): void {
        foreach ($fields as $field) {
            if (
                ! is_string($field)
                || ! isset($descriptor['fields'][$field])
            ) {
                $this->invalid(
                    'fields',
                    'contains an unsupported field.',
                );
            }
        }

        foreach ($relations as $relation) {
            if (
                ! is_string($relation)
                || ! isset($descriptor['relations'][$relation])
            ) {
                $this->invalid(
                    'relations',
                    'contains an unsupported relation.',
                );
            }
        }

        foreach ($request['group_by'] ?? [] as $field) {
            if (
                ! is_string($field)
                || ! isset($descriptor['fields'][$field])
            ) {
                $this->invalid(
                    'group_by',
                    'contains an unsupported field.',
                );
            }
        }

        foreach ($request['aggregates'] ?? [] as $aggregate) {
            if (! is_array($aggregate)) {
                $this->invalid(
                    'aggregates',
                    'must contain objects.',
                );
            }

            $this->only(
                $aggregate,
                ['operation', 'field'],
                'aggregates',
            );

            $operation = $aggregate['operation'] ?? null;
            $field = $aggregate['field'] ?? null;

            if (
                ! is_string($operation)
                || ! in_array($operation, ['count', 'sum'], true)
            ) {
                $this->invalid(
                    'aggregates',
                    'supports count and sum only.',
                );
            }

            if ($operation === 'count') {
                if (
                    $field !== null
                    && (
                        ! is_string($field)
                        || ! isset($descriptor['fields'][$field])
                    )
                ) {
                    $this->invalid(
                        'aggregates',
                        'count contains an unsupported field.',
                    );
                }

                continue;
            }

            if (
                ! is_string($field)
                || ! isset($descriptor['fields'][$field])
                || ! in_array(
                    $field,
                    $descriptor['summable'] ?? [],
                    true,
                )
            ) {
                $this->invalid(
                    'aggregates',
                    'sum requires an explicitly summable field.',
                );
            }
        }
    }

    /**
     * Determine whether the query touches any cost-sensitive field.
     *
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @param  array<string, mixed>  $request
     */
    private function requiresCosts(
        array $descriptor,
        array $fields,
        array $relations,
        array $request,
    ): bool {
        foreach (
            array_merge(
                $fields,
                $request['group_by'] ?? [],
            ) as $field
        ) {
            if (
                is_string($field)
                && $this->isCostField($descriptor, $field)
            ) {
                return true;
            }
        }

        foreach ($relations as $relation) {
            foreach (
                $descriptor['relations'][$relation]['fields']
                as $field
            ) {
                if (
                    is_array($field)
                    && ($field['cost'] ?? false) === true
                ) {
                    return true;
                }
            }
        }

        foreach ($request['sort'] ?? [] as $sort) {
            if (
                is_array($sort)
                && is_string($sort['field'] ?? null)
                && $this->isCostField(
                    $descriptor,
                    $sort['field'],
                )
            ) {
                return true;
            }
        }

        foreach ($request['aggregates'] ?? [] as $aggregate) {
            if (
                is_array($aggregate)
                && is_string($aggregate['field'] ?? null)
                && $this->isCostField(
                    $descriptor,
                    $aggregate['field'],
                )
            ) {
                return true;
            }
        }

        foreach ($request['filters'] ?? [] as $filter) {
            if (
                is_array($filter)
                && is_string($filter['field'] ?? null)
                && $this->isCostField(
                    $descriptor,
                    $filter['field'],
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the organization-scoped base Eloquent query.
     *
     * @param  array<string, mixed>  $descriptor
     * @return Builder<Model>
     */
    private function query(
        AiMcpExecutionIdentity $identity,
        array $descriptor,
    ): Builder {
        /** @var class-string<Model> $model */
        $model = $descriptor['model'];

        if ($model === StockBalance::class) {
            /** @var Builder<Model> $query */
            $query = $this->stockBalanceReportQuery->allBalances(
                $identity->organization,
                null,
                null,
                null,
                null,
                null,
            );

            return $query;
        }

        $query = $model::query();
        $scope = $descriptor['scope'];

        if ($scope === 'organization') {
            return $query->whereKey(
                $identity->organization->id,
            );
        }

        if ($scope === 'direct') {
            return $query->where(
                'organization_id',
                $identity->organization->id,
            );
        }

        if (str_starts_with($scope, 'parent:')) {
            return $query->whereHas(
                substr($scope, 7),
                fn (Builder $parent): Builder => $parent->where(
                    'organization_id',
                    $identity->organization->id,
                ),
            );
        }

        throw new \LogicException(
            'Unsupported catalog scope.',
        );
    }

    /**
     * Apply bounded allowlisted filter operators.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     * @param  list<mixed>  $filters
     */
    private function applyFilters(
        Builder $query,
        array $descriptor,
        array $filters,
    ): void {
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                $this->invalid(
                    'filters',
                    'must contain objects.',
                );
            }

            $this->only(
                $filter,
                ['field', 'operator', 'value'],
                'filters',
            );

            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? null;
            $value = $filter['value'] ?? null;

            if (
                ! is_string($field)
                || ! isset($descriptor['fields'][$field])
                || ! is_string($operator)
                || ! in_array(
                    $operator,
                    [
                        'eq',
                        'neq',
                        'gt',
                        'gte',
                        'lt',
                        'lte',
                        'contains',
                        'in',
                    ],
                    true,
                )
            ) {
                $this->invalid(
                    'filters',
                    'contains an unsupported field or operator.',
                );
            }

            $column = $this->column(
                $descriptor['fields'][$field],
            );

            if ($operator === 'in') {
                if (
                    ! is_array($value)
                    || ! array_is_list($value)
                    || $value === []
                    || count($value) > 25
                    || array_filter(
                        $value,
                        static fn (mixed $item): bool => is_array($item)
                            || is_object($item),
                    )
                ) {
                    $this->invalid(
                        'filters',
                        'in values must be a bounded scalar list.',
                    );
                }

                $query->whereIn($column, $value);

                continue;
            }

            if (
                is_array($value)
                || is_object($value)
                || (
                    ! is_scalar($value)
                    && $value !== null
                )
            ) {
                $this->invalid(
                    'filters',
                    'values must be scalar.',
                );
            }

            if ($operator === 'contains') {
                if (
                    ! is_string($value)
                    || mb_strlen($value) > 120
                ) {
                    $this->invalid(
                        'filters',
                        'contains requires a string up to 120 characters.',
                    );
                }

                $query->whereLike(
                    $column,
                    '%'.$value.'%',
                );

                continue;
            }

            $query->where(
                $column,
                [
                    'eq' => '=',
                    'neq' => '<>',
                    'gt' => '>',
                    'gte' => '>=',
                    'lt' => '<',
                    'lte' => '<=',
                ][$operator],
                $value,
            );
        }
    }

    /**
     * Apply allowlisted list-query sorting.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     * @param  list<mixed>  $sort
     */
    private function applySort(
        Builder $query,
        array $descriptor,
        array $sort,
    ): void {
        foreach ($sort as $item) {
            if (! is_array($item)) {
                $this->invalid(
                    'sort',
                    'must contain objects.',
                );
            }

            $this->only(
                $item,
                ['field', 'direction'],
                'sort',
            );

            if (
                ! is_string($item['field'] ?? null)
                || ! isset(
                    $descriptor['fields'][$item['field']]
                )
                || ! in_array(
                    $item['direction'] ?? 'asc',
                    ['asc', 'desc'],
                    true,
                )
            ) {
                $this->invalid(
                    'sort',
                    'contains an unsupported field or direction.',
                );
            }

            $query->orderBy(
                $this->column(
                    $descriptor['fields'][$item['field']],
                ),
                $item['direction'] ?? 'asc',
            );
        }

        if ($sort === []) {
            $query->orderByDesc('id');
        }
    }

    /**
     * Select requested public fields plus hidden relationship foreign keys.
     *
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @return list<string>
     */
    private function selectionColumns(
        array $descriptor,
        array $fields,
        array $relations,
    ): array {
        $columns = ['id'];

        foreach ($fields as $field) {
            $columns[] = $this->column(
                $descriptor['fields'][$field],
            );
        }

        foreach ($relations as $relation) {
            $foreignKey = $descriptor['relations'][$relation]['foreign_key']
                ?? null;

            if (! is_string($foreignKey)) {
                throw new \LogicException(
                    'Catalog relation is missing its foreign key.',
                );
            }

            $columns[] = $foreignKey;
        }

        return array_values(
            array_unique($columns),
        );
    }

    /**
     * Load only explicitly requested, allowlisted relationship fields.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $relations
     */
    private function loadRelations(
        Builder $query,
        array $descriptor,
        array $relations,
    ): void {
        foreach ($relations as $alias) {
            $relation = $descriptor['relations'][$alias];

            $columns = ['id'];

            foreach ($relation['fields'] as $field) {
                $columns[] = $this->column($field);
            }

            $columns = array_values(
                array_unique($columns),
            );

            $query->with([
                $relation['relation'] => static function (
                    Builder $relatedQuery,
                ) use ($columns): void {
                    $relatedQuery->select($columns);
                },
            ]);
        }
    }

    /**
     * Execute bounded count or sum aggregation with optional grouping.
     *
     * Every SQL identifier used below originates from the server-owned catalog,
     * never from a caller-provided database identifier or SQL expression.
     *
     * @param  array<string, mixed>  $descriptor
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function aggregate(
        AiMcpExecutionIdentity $identity,
        array $descriptor,
        array $request,
    ): array {
        $aggregates = $request['aggregates'] ?? [];
        $groupBy = $request['group_by'] ?? [];

        if ($aggregates === []) {
            $this->invalid(
                'aggregates',
                'requires at least one aggregate.',
            );
        }

        if (
            ($request['fields'] ?? []) !== []
            || ($request['relations'] ?? []) !== []
        ) {
            $this->invalid(
                'request',
                'aggregate queries cannot select fields or relations directly.',
            );
        }

        $aliases = [];

        foreach ($aggregates as $aggregate) {
            /** @var array<string, mixed> $aggregate */
            $alias = $this->aggregateAlias($aggregate);

            if (isset($aliases[$alias])) {
                $this->invalid(
                    'aggregates',
                    'contains duplicate aggregate output names.',
                );
            }

            $aliases[$alias] = true;
        }

        foreach ($request['sort'] ?? [] as $sort) {
            if (
                ! is_array($sort)
                || ! is_string($sort['field'] ?? null)
                || ! in_array(
                    $sort['field'],
                    $groupBy,
                    true,
                )
            ) {
                $this->invalid(
                    'sort',
                    'aggregate sorting is limited to group_by fields.',
                );
            }

            $this->only(
                $sort,
                ['field', 'direction'],
                'sort',
            );

            if (
                ! in_array(
                    $sort['direction'] ?? 'asc',
                    ['asc', 'desc'],
                    true,
                )
            ) {
                $this->invalid(
                    'sort',
                    'contains an unsupported direction.',
                );
            }
        }

        return $this->session->run(
            function () use (
                $identity,
                $descriptor,
                $request,
                $aggregates,
                $groupBy,
            ): array {
                $query = $this->query(
                    $identity,
                    $descriptor,
                );

                $query->setEagerLoads([]);

                $this->applyFilters(
                    $query,
                    $descriptor,
                    $request['filters'] ?? [],
                );

                $groupColumns = [];

                foreach ($groupBy as $field) {
                    $groupColumns[$field] = $this->column(
                        $descriptor['fields'][$field],
                    );
                }

                if ($groupColumns !== []) {
                    $query->select(
                        array_values($groupColumns),
                    );

                    $query->groupBy(
                        ...array_values($groupColumns),
                    );
                }

                foreach ($aggregates as $aggregate) {
                    /** @var array<string, mixed> $aggregate */
                    $operation = $aggregate['operation'];
                    $field = $aggregate['field'] ?? null;
                    $alias = $this->aggregateAlias($aggregate);

                    if ($operation === 'count') {
                        $expression = $field === null
                            ? 'COUNT(*)'
                            : 'COUNT('
                                .$this->column(
                                    $descriptor['fields'][$field],
                                )
                                .')';
                    } else {
                        $expression = 'COALESCE(SUM('
                            .$this->column(
                                $descriptor['fields'][$field],
                            )
                            .'), 0)';
                    }

                    $query->selectRaw(
                        $expression.' AS '.$alias,
                    );
                }

                if ($groupColumns === []) {
                    $row = $query->first();

                    if ($row === null) {
                        throw new \LogicException(
                            'Aggregate query did not return a result row.',
                        );
                    }

                    $result = [];

                    foreach ($aggregates as $aggregate) {
                        /** @var array<string, mixed> $aggregate */
                        $alias = $this->aggregateAlias($aggregate);

                        $result[$alias] = $this->aggregateValue(
                            $row->getAttribute($alias),
                            $aggregate['operation'],
                        );
                    }

                    return [
                        'aggregate' => $result,
                        'metadata' => [
                            'resource' => $request['resource'],
                        ],
                    ];
                }

                if (($request['sort'] ?? []) === []) {
                    foreach ($groupColumns as $column) {
                        $query->orderBy($column);
                    }
                } else {
                    foreach ($request['sort'] as $sort) {
                        /** @var array<string, mixed> $sort */
                        $query->orderBy(
                            $groupColumns[$sort['field']],
                            $sort['direction'] ?? 'asc',
                        );
                    }
                }

                $limit = $request['limit'] ?? self::DEFAULT_LIMIT;
                $page = $request['page'] ?? 1;

                $rows = $query
                    ->forPage($page, $limit + 1)
                    ->get();

                $truncated = $rows->count() > $limit;

                return [
                    'rows' => $rows
                        ->take($limit)
                        ->map(
                            function (Model $model) use (
                                $descriptor,
                                $groupBy,
                                $aggregates,
                            ): array {
                                $row = [];

                                foreach ($groupBy as $field) {
                                    $row[$field] = $this->value(
                                        $model->getAttribute(
                                            $this->column(
                                                $descriptor['fields'][$field],
                                            ),
                                        ),
                                    );
                                }

                                foreach ($aggregates as $aggregate) {
                                    /** @var array<string, mixed> $aggregate */
                                    $alias = $this->aggregateAlias(
                                        $aggregate,
                                    );

                                    $row[$alias] = $this->aggregateValue(
                                        $model->getAttribute($alias),
                                        $aggregate['operation'],
                                    );
                                }

                                return $row;
                            },
                        )
                        ->values()
                        ->all(),
                    'metadata' => [
                        'resource' => $request['resource'],
                        'page' => $page,
                        'limit' => $limit,
                        'returned_row_count' => min(
                            $rows->count(),
                            $limit,
                        ),
                        'truncated' => $truncated,
                    ],
                ];
            },
        );
    }

    /**
     * Produce a deterministic, server-owned aggregate result alias.
     *
     * @param  array<string, mixed>  $aggregate
     */
    private function aggregateAlias(array $aggregate): string
    {
        $operation = $aggregate['operation'];
        $field = $aggregate['field'] ?? null;

        if ($operation === 'count') {
            return $field === null
                ? 'count'
                : 'count_'.$field;
        }

        return 'sum_'.$field;
    }

    /**
     * Normalize aggregate result values without floating-point conversion.
     */
    private function aggregateValue(
        mixed $value,
        string $operation,
    ): int|string {
        if ($operation === 'count') {
            return (int) $value;
        }

        return $value === null
            ? '0'
            : (string) $value;
    }

    /**
     * Serialize one list-query model using only allowlisted selections.
     *
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @return array<string, mixed>
     */
    private function serialize(
        Model $model,
        array $descriptor,
        array $fields,
        array $relations,
    ): array {
        $row = [];

        foreach ($fields as $field) {
            $row[$field] = $this->value(
                $model->getAttribute(
                    $this->column(
                        $descriptor['fields'][$field],
                    ),
                ),
            );
        }

        foreach ($relations as $alias) {
            $relation = $descriptor['relations'][$alias];
            $related = $model->getRelation(
                $relation['relation'],
            );

            if ($related instanceof Model) {
                $row[$alias] = [];

                foreach (
                    $relation['fields']
                    as $name => $field
                ) {
                    $row[$alias][$name] = $this->value(
                        $related->getAttribute(
                            $this->column($field),
                        ),
                    );
                }

                continue;
            }

            $row[$alias] = null;
        }

        return $row;
    }

    /**
     * Resolve a public field descriptor to its server-owned database column.
     *
     * @param  string|array{column: string, cost?: bool}  $field
     */
    private function column(string|array $field): string
    {
        return is_array($field)
            ? $field['column']
            : $field;
    }

    /**
     * Determine whether one public field is cost-sensitive.
     *
     * @param  array<string, mixed>  $descriptor
     */
    private function isCostField(
        array $descriptor,
        string $field,
    ): bool {
        return isset($descriptor['fields'][$field])
            && is_array($descriptor['fields'][$field])
            && (
                $descriptor['fields'][$field]['cost']
                ?? false
            ) === true;
    }

    /**
     * Normalize Eloquent values for structured MCP output.
     */
    private function value(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(
                DateTimeInterface::ATOM,
            ),
            default => $value,
        };
    }

    /**
     * Reject unknown keys in every structured request object.
     *
     * @param  array<string, mixed>  $value
     * @param  list<string>  $allowed
     */
    private function only(
        array $value,
        array $allowed,
        string $attribute,
    ): void {
        if (
            array_diff(
                array_keys($value),
                $allowed,
            ) !== []
        ) {
            $this->invalid(
                $attribute,
                'contains an unknown key.',
            );
        }
    }

    /**
     * Throw a standard Laravel validation exception for an invalid query.
     */
    private function invalid(
        string $attribute,
        string $message,
    ): never {
        throw ValidationException::withMessages([
            $attribute => $message,
        ]);
    }
}
