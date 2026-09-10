<?php

namespace App\Http\Controllers;

use App\Enums\ProblemReportStatus;
use App\Http\Requests\CreateProblemReportRequest;
use App\Models\ProblemReport;
use App\Models\ProblemReportAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProblemReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $perPage = 10;

        $reports = ProblemReport::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);

        return Inertia::render('problem-reports/index', [
            'reports' => $reports,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('problem-reports/create');
    }

    public function store(CreateProblemReportRequest $request)
    {
        $user = $request->user();
        $rateLimitKey = "problem-report:{$user->id}";

        if (! RateLimiter::attempt($rateLimitKey, 10, fn () => 3600)) {
            abort(429, 'You can submit a maximum of 10 reports per hour.');
        }

        $activeOrganization = $request->attributes->get('activeOrganization');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($request, $user, $activeOrganization, &$storedPaths) {
                $reference = $this->generateReference($user->id);

                $problemReport = ProblemReport::create([
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'organization_id' => $activeOrganization?->id,
                    'organization_name_snapshot' => $activeOrganization?->name,
                    'title' => $request->string('title')->trim()->value() ?: null,
                    'description' => $request->string('description')->trim()->value(),
                    'status' => ProblemReportStatus::Submitted,
                ]);

                $screenshots = $request->file('screenshots') ?? [];
                foreach ($screenshots as $screenshot) {
                    $mimeType = $screenshot->getMimeType();
                    $path = Storage::disk('local')->putFileAs(
                        "problem-reports/{$problemReport->id}",
                        $screenshot,
                        Str::random(32).'.'.$screenshot->extension(),
                    );
                    $storedPaths[] = $path;

                    ProblemReportAttachment::create([
                        'problem_report_id' => $problemReport->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $screenshot->getClientOriginalName(),
                        'mime_type' => $mimeType,
                        'size' => $screenshot->getSize(),
                    ]);
                }

                return redirect()->route('problem-reports.show', $problemReport->reference);
            }, attempts: 1);
        } catch (\Exception $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    public function show(string $reference, Request $request): Response
    {
        $problemReport = ProblemReport::where('reference', $reference)
            ->with('attachments')
            ->firstOrFail();

        $this->authorize('view', $problemReport);

        return Inertia::render('problem-reports/show', [
            'report' => $problemReport,
        ]);
    }

    public function attachment(Request $request, string $reference, int $attachmentId): SymfonyResponse
    {
        $user = $request->user();

        $problemReport = ProblemReport::where('reference', $reference)
            ->firstOrFail();

        $this->authorize('viewAttachment', $problemReport);

        $attachment = ProblemReportAttachment::where('id', $attachmentId)
            ->where('problem_report_id', $problemReport->id)
            ->firstOrFail();

        try {
            $path = $attachment->path;
            if (! Storage::disk($attachment->disk)->exists($path)) {
                abort(404, 'File not found');
            }

            return response()->file(
                Storage::disk($attachment->disk)->path($path),
                ['Content-Type' => $attachment->mime_type]
            );
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    private function generateReference(int $userId): string
    {
        $date = now()->format('ymd');
        $random = Str::random(6);

        return "PR-{$date}-{$random}";
    }
}
