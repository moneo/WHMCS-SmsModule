<?php
/**
 * SMS Sender Interface
 *
 * All SMS provider implementations must implement this interface.
 *
 * @package    MoneoSms
 * @author     Guven Atbakan <guven@atbakan.com>
 * @license    GPLv3
 */
interface SmsSenderInterface
{
    /**
     * @param string $message    The message to send
     * @param string $gsmnumber  The recipient phone number
     */
    public function __construct(string $message, string $gsmnumber);

    /**
     * Send the SMS message.
     *
     * @return array{log: array, error: array, msgid: string|null}
     */
    public function send(): array;

    /**
     * Get the remaining account balance/credits.
     *
     * @return string|null
     */
    public function balance(): ?string;

    /**
     * Get delivery report for a message.
     *
     * @param string $msgId
     * @return string|null  Returns status string like 'success', 'error', 'pending'
     */
    public function report(string $msgId): ?string;

    /**
     * Provider-specific phone number formatting.
     *
     * @param string $number
     * @return string
     */
    public function utilgsmnumber(string $number): string;

    /**
     * Provider-specific message formatting.
     *
     * @param string $message
     * @return string
     */
    public function utilmessage(string $message): string;
}
