<?php

namespace App\Http\Controllers\Personal;

use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Models\personal\DocumentTemplate;
use App\Models\personal\DocumentType;
use App\Models\personal\PersonalDocument;
use App\Models\User;
use App\Services\Personal\PersonalDocumentService;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Http\Request;

class PersonalDocumentController extends Controller
{
    public function index(User $employe)
    {
        $employe = app(PersonalScopeService::class)->visibleEmployees()->findOrFail($employe->id);
        $this->authorize('view', new PersonalDocument(['employe_id' => $employe->id]));

        $documents = PersonalDocument::where('employe_id', $employe->id)
            ->with('documentType')
            ->latest()
            ->get()
            ->groupBy(fn ($d) => $d->documentType?->category ?? 'Sonstiges');

        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();

        return view('personal.documents.index', compact('employe', 'documents', 'documentTypes'));
    }

    public function upload(Request $request, User $employe)
    {
        $employe = app(PersonalScopeService::class)->visibleEmployees()->findOrFail($employe->id);
        $this->authorize('manage', new PersonalDocument(['employe_id' => $employe->id]));

        $request->validate([
            'file'             => 'required|file|max:20480',
            'document_type_id' => 'required|exists:pers_document_types,id',
            'title'            => 'required|string|max:255',
            'issue_date'       => 'nullable|date',
            'expiry_date'      => 'nullable|date|after:today',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $localPath = $request->file('file')->store('tmp/personal', 'local');
        $type      = DocumentType::findOrFail($request->document_type_id);

        app(PersonalDocumentService::class)->uploadToNextcloud(
            $employe,
            storage_path('app/' . $localPath),
            $type,
            [
                'title'         => $request->title,
                'issue_date'    => $request->issue_date,
                'expiry_date'   => $request->expiry_date,
                'reminder_days' => $type->default_reminder_days,
                'notes'         => $request->notes,
            ]
        );

        return redirectBack()
            ->with('Meldung', 'Dokument wird hochgeladen (läuft im Hintergrund).')
            ->with('type', 'info');
    }

    public function download(PersonalDocument $document)
    {
        $this->authorize('view', $document);

        return app(PersonalDocumentService::class)->downloadFromNextcloud($document);
    }

    public function generate(Request $request, User $employe)
    {
        $this->authorize('create personal_documents');
        $employe = app(PersonalScopeService::class)->visibleEmployees()->findOrFail($employe->id);

        $request->validate([
            'template_id' => 'required|exists:pers_document_templates,id',
            'data'        => 'nullable|array',
        ]);

        $template  = DocumentTemplate::findOrFail($request->template_id);
        $localPath = app(PersonalDocumentService::class)
            ->generateFromTemplate($template, $employe, $request->data ?? []);

        app(PersonalDocumentService::class)->uploadToNextcloud(
            $employe,
            $localPath,
            $template->documentType
        );

        return redirectBack()
            ->with('Meldung', 'Dokument wird generiert und hochgeladen.')
            ->with('type', 'success');
    }

    public function destroy(PersonalDocument $document)
    {
        $this->authorize('delete', $document);

        $document->delete();

        return redirectBack()
            ->with('Meldung', 'Dokument wurde gelöscht.')
            ->with('type', 'success');
    }

    public function syncErrors()
    {
        $this->authorize('manage personal_documents');

        $documents = PersonalDocument::where('sync_status', SyncStatus::SyncFehler->value)
            ->with(['employe', 'documentType'])
            ->latest()
            ->paginate(20);

        return view('personal.documents.sync-errors', compact('documents'));
    }

    public function retrySync(PersonalDocument $document)
    {
        $this->authorize('manage', $document);

        $document->update(['sync_status' => SyncStatus::Pending->value]);

        \App\Jobs\Personal\UploadDocumentToNextcloud::dispatch(
            $document,
            storage_path('app/tmp/retry_' . $document->id)
        );

        return redirectBack()
            ->with('Meldung', 'Sync wird erneut versucht.')
            ->with('type', 'info');
    }
}

