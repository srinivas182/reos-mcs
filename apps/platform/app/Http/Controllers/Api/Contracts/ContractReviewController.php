<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Contracts;

use App\ContractReview\ContractReviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractQuestionRequest;
use App\Http\Requests\Contracts\StartReviewRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/** Contract review (A12.2, ADR 0048; agent screens 63–66, web screen 15). Private to the agent. */
final class ContractReviewController extends Controller
{
    /**
     * My contract reviews.
     *
     * Newest first, with what a review costs, my credits left, whether the sample contract is offered, and my deals' documents to choose from.
     *
     * @response array{data: array{reviews: list<array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}>, addenda: list<string>, findings: list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>, deadlines: list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}>, cost: int, credits_left: int, sample: bool, files: list<array{uuid: string, name: string, deal: string, added_at: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, ContractReviews $reviews): JsonResponse
    {
        return response()->json(['data' => $reviews->overview($user)]);
    }

    /**
     * Review a contract.
     *
     * A PDF or photo (`file`, up to 20 MB), a deal's document (`deal_file`), or the sample TREC 20-18 outside production (`sample`). A new PDF with `deal_id` is kept with the deal. The review runs in the background (status "reviewing", then "done" or "failed", with a notification); credits are charged only when it's done.
     *
     * @status 201
     *
     * @response array{data: array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}>, addenda: list<string>, findings: list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>, deadlines: list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}}
     */
    public function store(StartReviewRequest $request, #[CurrentUser] User $user, ContractReviews $reviews): JsonResponse
    {
        $file = $request->file('file');
        $deal = $request->integer('deal_id');
        $review = $reviews->start($user, $file instanceof UploadedFile ? $file : null, $request->filled('deal_file') ? $request->string('deal_file')->toString() : null, $deal > 0 ? $deal : null, $request->boolean('sample'));

        return response()->json(['data' => $reviews->row($reviews->own($user, $review->uuid))], 201);
    }

    /**
     * One review.
     *
     * @response array{data: array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}>, addenda: list<string>, findings: list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>, deadlines: list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}}
     */
    public function show(string $review, #[CurrentUser] User $user, ContractReviews $reviews): JsonResponse
    {
        return response()->json(['data' => $reviews->row($reviews->own($user, $review))]);
    }

    /**
     * Add the deadlines to my calendar.
     *
     * On the deal as key dates (with to-dos), or as to-dos with their days when the review has no deal. Done once.
     *
     * @response array{data: array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}>, addenda: list<string>, findings: list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>, deadlines: list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}}
     */
    public function deadlines(string $review, #[CurrentUser] User $user, ContractReviews $reviews): JsonResponse
    {
        $reviews->addDeadlines($reviews->own($user, $review));

        return response()->json(['data' => $reviews->row($reviews->own($user, $review))]);
    }

    /**
     * Send to my broker.
     *
     * The findings and deadlines go to the broker as a virtual broker question, to confirm or correct. Done once.
     *
     * @response array{data: array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}>, addenda: list<string>, findings: list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>, deadlines: list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}}
     */
    public function broker(string $review, #[CurrentUser] User $user, ContractReviews $reviews): JsonResponse
    {
        $reviews->sendToBroker($reviews->own($user, $review));

        return response()->json(['data' => $reviews->row($reviews->own($user, $review))]);
    }

    /**
     * Ask about this contract.
     *
     * An answer from the contract's own pages, naming them, and a note for the client. 1 credit.
     *
     * @response array{data: array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}>, addenda: list<string>, findings: list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>, deadlines: list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}}
     */
    public function ask(ContractQuestionRequest $request, string $review, #[CurrentUser] User $user, ContractReviews $reviews): JsonResponse
    {
        $reviews->ask($reviews->own($user, $review), $request->string('question')->toString());

        return response()->json(['data' => $reviews->row($reviews->own($user, $review))]);
    }
}
