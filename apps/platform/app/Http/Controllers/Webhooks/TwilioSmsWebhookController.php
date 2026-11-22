<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Notifications\Centre\Sms\TwilioReplies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `POST /webhooks/twilio-sms`: a text sent to REOS's number (B6.2c). Signed
 * by Twilio; STOP, START and HELP are handled, anything else is ignored.
 * Answers with TwiML.
 */
final class TwilioSmsWebhookController extends Controller
{
    public function __invoke(Request $request, TwilioReplies $replies): Response
    {
        /** @var array<string, mixed> $fields */
        $fields = $request->request->all();

        if (! $replies->signed($request->fullUrl(), $fields, $request->header('X-Twilio-Signature'))) {
            return response('', 403);
        }

        $from = $request->string('From')->toString();
        $reply = preg_match('/^\+[1-9][0-9]{7,14}$/', $from) === 1 ? $replies->handle($from, $request->string('Body')->toString()) : null;
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .($reply === null ? '' : '<Message>'.htmlspecialchars($reply, ENT_XML1).'</Message>')
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }
}
