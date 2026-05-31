<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\StoreProcedureStepCommentRequest;
use App\Models\Procedure_Step;
use App\Models\ProcedureStepComment;
use App\Services\Procedure\ProcedureNotificationService;
use Illuminate\Http\JsonResponse;

/**
 * Kommentar-CRUD an Prozess-Schritten (§4.1 B-19, §8.3).
 */
class ProcedureStepCommentController extends Controller
{
    public function __construct(private readonly ProcedureNotificationService $notifications) {}

    public function index(Procedure_Step $step): JsonResponse
    {
        $this->authorize('view', $step);

        $comments = $step->comments()->with('user')->get()->map(fn (ProcedureStepComment $c) => [
            'id'         => $c->id,
            'body'       => $c->body,
            'created_at' => $c->created_at->toAtomString(),
            'author'     => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
            'is_mine'    => $c->user_id === auth()->id(),
        ]);

        return response()->json(['data' => $comments]);
    }

    public function store(StoreProcedureStepCommentRequest $request, Procedure_Step $step): JsonResponse
    {
        $comment = ProcedureStepComment::create([
            'step_id' => $step->id,
            'user_id' => $request->user()->id,
            'body'    => $request->validated()['body'],
        ]);

        $sent = $this->notifications->notifyComment($comment);

        return response()->json([
            'data' => [
                'id'         => $comment->id,
                'body'       => $comment->body,
                'created_at' => $comment->created_at->toAtomString(),
                'author'     => ['id' => $request->user()->id, 'name' => $request->user()->name],
                'notified'   => $sent,
            ],
        ], 201);
    }

    public function destroy(Procedure_Step $step, ProcedureStepComment $comment): JsonResponse
    {
        abort_unless($comment->step_id === $step->id, 404);

        $user = auth()->user();
        if (!$user || ($comment->user_id !== $user->id && !$user->can('manage procedures'))) {
            abort(403, 'Nur der Autor oder ein Admin darf den Kommentar löschen.');
        }

        $comment->delete();

        return response()->json(['status' => 'ok']);
    }
}

