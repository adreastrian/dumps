<?php

namespace LaraDumps\LaraDumpsCore\Payloads;
use PHPMailer\PHPMailer\PHPMailer;

class MailPayload extends Payload
{
    public const MAX_ATTACH_BINARY_FILE_IN_MB = 25;

    protected array $mailProperties = [];
    private string $screen = 'mail';
    private string $label;

    private $notificationId;

    public function __construct(PHPMailer $phpMailer = null, array $mailData = [], string $messageId = '', string $screen = 'mail', string $label = '')
    {
        if ($phpMailer === null) {
            // This is for the fromWpMail factory method
            return;
        }

        $this->screen = $screen;
        $this->label = $label;
        $html = $phpMailer->Body ?? '';
        $textBody = $phpMailer->AltBody ?? '';

        // Get attachments from PHPMailer
        $attachmentsData = $this->processAttachments($phpMailer);

        // Get headers
        $headers = $this->extractHeaders($phpMailer);

        $notificationId = $messageId ?: $this->generateMessageId();
        $this->notificationId = $notificationId;
        $this->mailProperties = [
            'messageId' => $messageId ?: $this->generateMessageId(),
            'html' => $html,
            'textBody' => $textBody,
            'subject' => $phpMailer->Subject ?? '',
            'to' => $this->formatAddresses($this->getToAddresses($phpMailer)),
            'cc' => $this->formatAddresses($this->getCcAddresses($phpMailer)),
            'bcc' => $this->formatAddresses($this->getBccAddresses($phpMailer)),
            'from' => $phpMailer->From ?? '',
            'fromName' => $phpMailer->FromName ?? '',
            'replyTo' => $this->formatAddresses($this->getReplyToAddresses($phpMailer)),
            'details' => $mailData,
            'attachments' => $attachmentsData,
            'headers' => $headers,
        ];
    }

    /**
     * Alternative constructor for wp_mail hook data
     */
    public static function fromWpMail(
        $to,
        string $subject,
        string $message,
        array $headers = [],
        array $attachments = [],
        string $messageId = '',
        string $screen = 'mail',
        string $label = ''
    ): self {
        $mailData = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
            'wp_mail_data' => true
        ];

        $instance = new self();
        $instance->screen = $screen;
        $instance->label = $label;

        // Parse headers to extract from, content-type, etc.
        $parsedHeaders = self::parseWpMailHeaders($headers);
        $fromEmail = $parsedHeaders['from'] ?? get_option('admin_email');
        $fromName = $parsedHeaders['from_name'] ?? get_bloginfo('name');

        // Format to addresses
        $toAddresses = is_array($to) ? $to : [$to];
        $formattedTo = [];
        foreach ($toAddresses as $email) {
            $formattedTo[] = ['email' => $email, 'name' => ''];
        }

        $instance->notificationId = $messageId ?: $instance->generateMessageId();

        $instance->mailProperties = [
            'messageId' => $instance->notificationId,
            'html' => $message,
            'textBody' => strip_tags($message),
            'subject' => $subject,
            'to' => $formattedTo,
            'cc' => [],
            'bcc' => [],
            'from' => $fromEmail,
            'fromName' => $fromName,
            'replyTo' => [],
            'details' => $mailData,
            'attachments' => $instance->processWpMailAttachments($attachments),
            'headers' => $headers,
        ];

        return $instance;
    }

    private function processAttachments(PHPMailer $phpMailer): array
    {
        $attachmentsData = [];

        // Access attachments via reflection since getAttachments() might not be available
        try {
            $reflection = new \ReflectionClass($phpMailer);
            if ($reflection->hasProperty('attachment')) {
                $attachmentProperty = $reflection->getProperty('attachment');
                $attachmentProperty->setAccessible(true);
                $attachments = $attachmentProperty->getValue($phpMailer);

                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        $filePath = $attachment[0] ?? '';
                        $filename = $attachment[2] ?? basename($filePath);

                        if (!file_exists($filePath)) {
                            continue;
                        }

                        $fileSize = filesize($filePath);
                        $body = null;

                        // Only read file content if it's under the size limit
                        if ($fileSize <= (self::MAX_ATTACH_BINARY_FILE_IN_MB * 1024 * 1024)) {
                            $body = base64_encode(file_get_contents($filePath));
                        }

                        $attachmentsData[] = [
                            'body' => $body,
                            'path' => $filePath,
                            'filename' => $filename,
                            'size' => $fileSize,
                            'mime_type' => $this->getMimeType($filePath),
                        ];
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Silently continue if we can't access attachments
        }

        return $attachmentsData;
    }

    private function processWpMailAttachments(array $attachments): array
    {
        $attachmentsData = [];

        foreach ($attachments as $attachment) {
            if (!is_string($attachment) || !file_exists($attachment)) {
                continue;
            }

            $fileSize = filesize($attachment);
            $filename = basename($attachment);
            $body = null;

            // Only read file content if it's under the size limit
            if ($fileSize <= (self::MAX_ATTACH_BINARY_FILE_IN_MB * 1024 * 1024)) {
                $body = base64_encode(file_get_contents($attachment));
            }

            $attachmentsData[] = [
                'body' => $body,
                'path' => $attachment,
                'filename' => $filename,
                'size' => $fileSize,
                'mime_type' => $this->getMimeType($attachment),
            ];
        }

        return $attachmentsData;
    }

    private function getMimeType(string $filePath): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath) ?: 'application/octet-stream';
        }

        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType ?: 'application/octet-stream';
        }

        // Fallback based on file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    private function extractHeaders(PHPMailer $phpMailer): array
    {
        $headers = [];

        // Get common headers
        if (!empty($phpMailer->ContentType)) {
            $headers['Content-Type'] = $phpMailer->ContentType;
        }

        if (!empty($phpMailer->Encoding)) {
            $headers['Content-Transfer-Encoding'] = $phpMailer->Encoding;
        }

        if (!empty($phpMailer->MessageID)) {
            $headers['Message-ID'] = $phpMailer->MessageID;
        }

        if (!empty($phpMailer->MessageDate)) {
            $headers['Date'] = $phpMailer->MessageDate;
        }

        // Access custom headers via reflection since getCustomHeaders() doesn't exist in older versions
        try {
            $reflection = new \ReflectionClass($phpMailer);
            if ($reflection->hasProperty('CustomHeader')) {
                $customHeaderProperty = $reflection->getProperty('CustomHeader');
                $customHeaderProperty->setAccessible(true);
                $customHeaders = $customHeaderProperty->getValue($phpMailer);

                if (is_array($customHeaders)) {
                    foreach ($customHeaders as $header) {
                        if (is_array($header) && count($header) >= 2) {
                            $headers[$header[0]] = $header[1];
                        }
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Silently continue if we can't access custom headers
        }

        return $headers;
    }

    private function formatAddresses(array $addresses): array
    {
        $formatted = [];

        foreach ($addresses as $address) {
            $formatted[] = [
                'email' => $address[0],
                'name' => $address[1] ?? '',
            ];
        }

        return $formatted;
    }

    private function getToAddresses(PHPMailer $phpMailer): array
    {
        try {
            $reflection = new \ReflectionClass($phpMailer);
            if ($reflection->hasProperty('to')) {
                $toProperty = $reflection->getProperty('to');
                $toProperty->setAccessible(true);
                return $toProperty->getValue($phpMailer) ?? [];
            }
        } catch (\ReflectionException $e) {
            // Fallback: try to parse from headers or return empty
        }
        return [];
    }

    private function getCcAddresses(PHPMailer $phpMailer): array
    {
        try {
            $reflection = new \ReflectionClass($phpMailer);
            if ($reflection->hasProperty('cc')) {
                $ccProperty = $reflection->getProperty('cc');
                $ccProperty->setAccessible(true);
                return $ccProperty->getValue($phpMailer) ?? [];
            }
        } catch (\ReflectionException $e) {
            // Fallback
        }
        return [];
    }

    private function getBccAddresses(PHPMailer $phpMailer): array
    {
        try {
            $reflection = new \ReflectionClass($phpMailer);
            if ($reflection->hasProperty('bcc')) {
                $bccProperty = $reflection->getProperty('bcc');
                $bccProperty->setAccessible(true);
                return $bccProperty->getValue($phpMailer) ?? [];
            }
        } catch (\ReflectionException $e) {
            // Fallback
        }
        return [];
    }

    private function getReplyToAddresses(PHPMailer $phpMailer): array
    {
        try {
            $reflection = new \ReflectionClass($phpMailer);
            if ($reflection->hasProperty('ReplyTo')) {
                $replyToProperty = $reflection->getProperty('ReplyTo');
                $replyToProperty->setAccessible(true);
                return $replyToProperty->getValue($phpMailer) ?? [];
            }
        } catch (\ReflectionException $e) {
            // Fallback
        }
        return [];
    }

    private static function parseWpMailHeaders(array $headers): array
    {
        $parsed = [
            'from' => '',
            'from_name' => '',
            'content_type' => 'text/plain',
        ];

        foreach ($headers as $header) {
            if (is_string($header)) {
                if (stripos($header, 'From:') === 0) {
                    $from = trim(substr($header, 5));
                    if (preg_match('/(.+?)\s*<(.+?)>/', $from, $matches)) {
                        $parsed['from_name'] = trim($matches[1], '"');
                        $parsed['from'] = $matches[2];
                    } else {
                        $parsed['from'] = $from;
                    }
                } elseif (stripos($header, 'Content-Type:') === 0) {
                    $parsed['content_type'] = trim(substr($header, 13));
                }
            }
        }

        return $parsed;
    }

    private function generateMessageId(): string
    {
        return uniqid('wp_mail_') . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
    }

    public function type(): string
    {
        return 'mail';
    }

    public function content(): array
    {
        return $this->mailProperties;
    }

    public function toScreen()
    {
        return new Screen($this->screen);
    }

    public function withLabel()
    {
        return new Label($this->label);
    }
}