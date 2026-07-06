<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Notification;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Store a new comment for a project request.
     */
    public function store(Request $request, ProjectRequest $projectRequest): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $comment = Comment::create([
            'content'        => $request->content,
            'user_id'        => auth()->id(),
            'reference_id'   => $projectRequest->id,
            'reference_type' => ProjectRequest::class,
            'status'         => 'active',
        ]);

        $comment->load('user');

        $link = route('requests.show', $projectRequest->id, absolute: false);
        $message = "New Comment was added to project request #{$projectRequest->request_no}";

        if (auth()->id() === $projectRequest->requester_id) {
            Notification::notify(
                User::whereHas('roles', fn ($q) => $q->where('name', 'approver'))->pluck('id'),
                $message,
                $link
            );
        } else {
            Notification::notify($projectRequest->requester_id, $message, $link);
        }

        return response()->json([
            'id'      => $comment->id,
            'content' => $comment->content,
            'author'  => $comment->user->name ?? 'Unknown',
            'date'    => $comment->created_at->format('M d, H:i'),
        ]);
    }

    /**
     * Fetch comments for a project request.
     */
    public function index(ProjectRequest $projectRequest): JsonResponse
    {
        $comments = Comment::where('reference_id', $projectRequest->id)
            ->where('reference_type', ProjectRequest::class)
            ->where('status', 'active')
            ->with('user')
            ->oldest()
            ->get()
            ->map(fn($c) => [
                'id'      => $c->id,
                'content' => $c->content,
                'author'  => $c->user->name ?? 'Unknown',
                'date'    => $c->created_at->format('M d, H:i'),
            ]);

        return response()->json($comments);
    }

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        // Only allow the owner to delete
        if ($comment->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true]);
    }
}