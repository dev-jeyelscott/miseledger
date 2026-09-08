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

final class OrganizationDataQueryExecutor
{
    private const int DEFAULT_LIMIT = 50;

    private const int MAX_LIMIT = 200;

    /** @var list<string> */
    private const array ROOT_KEYS = ['operation', 'resource', 'fields', 'filters', 'relations', 'aggregates', 'group_by', 'sort', 'page', 'limit'];

    public function __construct(
        private readonly OrganizationDataCatalog $catalog,
        private readonly ReadOnlyOrganizationDataSession $session,
        private readonly StockBalanceReportQuery $stockBalanceReportQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(AiMcpExecutionIdentity $identity, array $input): array
    {
        $request = $this->validateRequest($input);
        $descriptor = $this->resource($request['resource']);
        $this->authorize($identity, $descriptor);

        $fields = $request['fields'] ?? array_keys(array_filter($descriptor['fields'], static fn (mixed $field): bool => ! is_array($field) || ($field['cost'] ?? false) !== true));
        $relations = $request['relations'] ?? [];
        $this->validateSelection($descriptor, $fields, $relations, $request);

        if ($this->requiresCosts($descriptor, $fields, $relations, $request) && ! Gate::forUser($identity->user)->allows('costs.view', $identity->organization)) {
            throw new AuthorizationException('Cost and valuation fields require costs.view permission.');
        }

        if ($request['operation'] === 'aggregate') {
            return $this->aggregate($identity, $descriptor, $request);
        }

        return $this->session->run(function () use ($identity, $descriptor, $request, $fields, $relations): array {
            $query = $this->query($identity, $descriptor);
            $this->applyFilters($query, $descriptor, $request['filters'] ?? []);
            $this->applySort($query, $descriptor, $request['sort'] ?? []);

            $columns = array_unique(array_merge(['id'], array_map(fn (string $field): string => $this->column($descriptor['fields'][$field]), $fields)));
            $query->select($columns);
            $this->loadRelations($query, $descriptor, $relations);

            $limit = $request['limit'] ?? self::DEFAULT_LIMIT;
            $page = $request['page'] ?? 1;
            $rows = $query->forPage($page, $limit + 1)->get();
            $truncated = $rows->count() > $limit;

            return [
                'rows' => $rows->take($limit)->map(fn (Model $model): array => $this->serialize($model, $descriptor, $fields, $relations))->values()->all(),
                'metadata' => [
                    'resource' => $request['resource'],
                    'page' => $page,
                    'limit' => $limit,
                    'returned_row_count' => min($rows->count(), $limit),
                    'truncated' => $truncated,
                ],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateRequest(array $input): array
    {
        $this->only($input, self::ROOT_KEYS, 'request');

        if (($input['operation'] ?? 'list') !== 'list' && ($input['operation'] ?? null) !== 'aggregate') {
            $this->invalid('operation', 'must be list or aggregate.');
        }
        if (! is_string($input['resource'] ?? null)) {
            $this->invalid('resource', 'is required.');
        }
        foreach (['fields', 'relations', 'group_by'] as $key) {
            if (isset($input[$key]) && (! is_array($input[$key]) || ! array_is_list($input[$key]))) {
                $this->invalid($key, 'must be a list.');
            }
        }
        if (isset($input['filters']) && (! is_array($input['filters']) || ! array_is_list($input['filters']))) {
            $this->invalid('filters', 'must be a list.');
        }
        if (isset($input['sort']) && (! is_array($input['sort']) || ! array_is_list($input['sort']))) {
            $this->invalid('sort', 'must be a list.');
        }
        if (isset($input['aggregates']) && (! is_array($input['aggregates']) || ! array_is_list($input['aggregates']))) {
            $this->invalid('aggregates', 'must be a list.');
        }
        foreach (['page' => 1000, 'limit' => self::MAX_LIMIT] as $key => $maximum) {
            if (isset($input[$key]) && (! is_int($input[$key]) || $input[$key] < 1 || $input[$key] > $maximum)) {
                $this->invalid($key, "must be an integer between 1 and {$maximum}.");
            }
        }
        if (count($input['fields'] ?? []) > 20 || count($input['filters'] ?? []) > 8 || count($input['relations'] ?? []) > 3 || count($input['sort'] ?? []) > 3 || count($input['aggregates'] ?? []) > 2 || count($input['group_by'] ?? []) > 2) {
            $this->invalid('request', 'exceeds the query complexity limit.');
        }

        return ['operation' => $input['operation'] ?? 'list', ...$input];
    }

    /** @return array<string, mixed> */
    private function resource(string $resource): array
    {
        $resources = $this->catalog->resources();

        if (! isset($resources[$resource])) {
            $this->invalid('resource', 'is not supported.');
        }

        return $resources[$resource];
    }

    /** @param array<string, mixed> $descriptor */
    private function authorize(AiMcpExecutionIdentity $identity, array $descriptor): void
    {
        foreach ($descriptor['permissions'] as $permission) {
            if (Gate::forUser($identity->user)->allows($permission->value, $identity->organization)) {
                if (($descriptor['feature'] ?? null) === null || OrganizationFeatureEntitlement::isGranted($identity->organization, $descriptor['feature'])) {
                    return;
                }

                throw new AuthorizationException('This resource is not included in the organization\'s current plan.');
            }
        }

        throw new AuthorizationException('You are not authorized to query this organization resource.');
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  list<mixed>  $fields
     * @param  list<mixed>  $relations
     * @param  array<string, mixed>  $request
     */
    private function validateSelection(array $descriptor, array $fields, array $relations, array $request): void
    {
        foreach ($fields as $field) {
            if (! is_string($field) || ! isset($descriptor['fields'][$field])) {
                $this->invalid('fields', 'contains an unsupported field.');
            }
        }
        foreach ($relations as $relation) {
            if (! is_string($relation) || ! isset($descriptor['relations'][$relation])) {
                $this->invalid('relations', 'contains an unsupported relation.');
            }
        }
        foreach ($request['group_by'] ?? [] as $field) {
            if (! is_string($field) || ! isset($descriptor['fields'][$field])) {
                $this->invalid('group_by', 'contains an unsupported field.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @param  array<string, mixed>  $request
     */
    private function requiresCosts(array $descriptor, array $fields, array $relations, array $request): bool
    {
        foreach (array_merge($fields, $request['group_by'] ?? []) as $field) {
            if (is_string($field) && $this->isCostField($descriptor, $field)) {
                return true;
            }
        }
        foreach ($relations as $relation) {
            foreach ($descriptor['relations'][$relation]['fields'] as $field) {
                if (is_array($field) && ($field['cost'] ?? false) === true) {
                    return true;
                }
            }
        }
        foreach ($request['sort'] ?? [] as $sort) {
            if (is_array($sort) && is_string($sort['field'] ?? null) && $this->isCostField($descriptor, $sort['field'])) {
                return true;
            }
        }
        foreach ($request['aggregates'] ?? [] as $aggregate) {
            if (is_array($aggregate) && is_string($aggregate['field'] ?? null) && $this->isCostField($descriptor, $aggregate['field'])) {
                return true;
            }
        }
        foreach ($request['filters'] ?? [] as $filter) {
            if (is_array($filter) && is_string($filter['field'] ?? null) && $this->isCostField($descriptor, $filter['field'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @return Builder<Model>
     */
    private function query(AiMcpExecutionIdentity $identity, array $descriptor): Builder
    {
        /** @var class-string<Model> $model */
        $model = $descriptor['model'];

        if ($model === StockBalance::class) {
            /** @var Builder<Model> $query */
            $query = $this->stockBalanceReportQuery->stockOnHand(
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
            return $query->whereKey($identity->organization->id);
        }
        if ($scope === 'direct') {
            return $query->where('organization_id', $identity->organization->id);
        }
        if (str_starts_with($scope, 'parent:')) {
            return $query->whereHas(substr($scope, 7), fn (Builder $parent): Builder => $parent->where('organization_id', $identity->organization->id));
        }

        throw new \LogicException('Unsupported catalog scope.');
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     * @param  list<mixed>  $filters
     */
    private function applyFilters(Builder $query, array $descriptor, array $filters): void
    {
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                $this->invalid('filters', 'must contain objects.');
            }
            $this->only($filter, ['field', 'operator', 'value'], 'filters');
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? null;
            $value = $filter['value'] ?? null;
            if (! is_string($field) || ! isset($descriptor['fields'][$field]) || ! is_string($operator) || ! in_array($operator, ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'in'], true)) {
                $this->invalid('filters', 'contains an unsupported field or operator.');
            }
            $column = $this->column($descriptor['fields'][$field]);
            if ($operator === 'in') {
                if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > 25 || array_filter($value, static fn (mixed $item): bool => is_array($item) || is_object($item))) {
                    $this->invalid('filters', 'in values must be a bounded scalar list.');
                }
                $query->whereIn($column, $value);

                continue;
            }
            if (is_array($value) || is_object($value) || (! is_scalar($value) && $value !== null)) {
                $this->invalid('filters', 'values must be scalar.');
            }
            if ($operator === 'contains') {
                if (! is_string($value) || mb_strlen($value) > 120) {
                    $this->invalid('filters', 'contains requires a string up to 120 characters.');
                }
                $query->whereLike($column, '%'.$value.'%');

                continue;
            }
            $query->where($column, ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='][$operator], $value);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     * @param  list<mixed>  $sort
     */
    private function applySort(Builder $query, array $descriptor, array $sort): void
    {
        foreach ($sort as $item) {
            if (! is_array($item)) {
                $this->invalid('sort', 'must contain objects.');
            }
            $this->only($item, ['field', 'direction'], 'sort');
            if (! is_string($item['field'] ?? null) || ! isset($descriptor['fields'][$item['field']]) || ! in_array($item['direction'] ?? 'asc', ['asc', 'desc'], true)) {
                $this->invalid('sort', 'contains an unsupported field or direction.');
            }
            $query->orderBy($this->column($descriptor['fields'][$item['field']]), $item['direction'] ?? 'asc');
        }
        if ($sort === []) {
            $query->orderByDesc('id');
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $relations
     */
    private function loadRelations(Builder $query, array $descriptor, array $relations): void
    {
        foreach ($relations as $alias) {
            $relation = $descriptor['relations'][$alias];
            $query->with($relation['relation']);
        }
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function aggregate(AiMcpExecutionIdentity $identity, array $descriptor, array $request): array
    {
        if (($request['group_by'] ?? []) !== [] || count($request['aggregates'] ?? []) !== 1) {
            $this->invalid('aggregates', 'supports exactly one ungrouped aggregate.');
        }
        $aggregate = $request['aggregates'][0];
        if (! is_array($aggregate)) {
            $this->invalid('aggregates', 'must contain objects.');
        }
        $this->only($aggregate, ['operation', 'field'], 'aggregates');
        if (($aggregate['operation'] ?? null) !== 'count' || isset($aggregate['field'])) {
            $this->invalid('aggregates', 'supports count only.');
        }

        return $this->session->run(function () use ($identity, $descriptor, $request): array {
            $query = $this->query($identity, $descriptor);
            $this->applyFilters($query, $descriptor, $request['filters'] ?? []);

            return ['aggregate' => ['count' => $query->count()], 'metadata' => ['resource' => $request['resource']]];
        });
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @return array<string, mixed>
     */
    private function serialize(Model $model, array $descriptor, array $fields, array $relations): array
    {
        $row = [];
        foreach ($fields as $field) {
            $row[$field] = $this->value($model->getAttribute($this->column($descriptor['fields'][$field])));
        }
        foreach ($relations as $alias) {
            $relation = $descriptor['relations'][$alias];
            $related = $model->getRelation($relation['relation']);
            if ($related instanceof Model) {
                $row[$alias] = [];
                foreach ($relation['fields'] as $name => $field) {
                    $row[$alias][$name] = $this->value($related->getAttribute($this->column($field)));
                }
            } else {
                $row[$alias] = null;
            }
        }

        return $row;
    }

    /** @param string|array{column: string, cost?: bool} $field */
    private function column(string|array $field): string
    {
        return is_array($field) ? $field['column'] : $field;
    }

    /** @param array<string, mixed> $descriptor */
    private function isCostField(array $descriptor, string $field): bool
    {
        return isset($descriptor['fields'][$field])
            && is_array($descriptor['fields'][$field])
            && ($descriptor['fields'][$field]['cost'] ?? false) === true;
    }

    private function value(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $allowed
     */
    private function only(array $value, array $allowed, string $attribute): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) {
            $this->invalid($attribute, 'contains an unknown key.');
        }
    }

    private function invalid(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
