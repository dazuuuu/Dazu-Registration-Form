<?php
// app/v1/services/StaffReminderEmail.php — the simple Staff Calendar reminder email: the Dazu logo, the short text and a green "Open Staff Calendar" button.
// It only builds [subject, html]. Sending is done with the existing Mailer (which embeds the logo when the HTML uses cid:ismanlogo). Every value is escaped.

class StaffReminderEmail
{
    public const GREEN = '#048C44';

    private static function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

    /** @return array{0:string,1:string} [subject, html] */
    public static function render(string $heading, string $text, string $url, string $firstName = ''): array
    {
        $e = [self::class, 'e'];
        $hi = $firstName !== '' ? '<p style="margin:0 0 12px;font-size:15px;color:#1d1b1c">Hi ' . $e($firstName) . ',</p>' : '';
        $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f3f5f1;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f1"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden">'
            . '<tr><td style="padding:22px 26px 8px"><img src="cid:ismanlogo" alt="Dazu Training Institute" height="44" style="display:block;border:0"></td></tr>'
            . '<tr><td style="padding:6px 26px 4px"><h1 style="margin:0 0 12px;font-size:20px;color:' . self::GREEN . '">' . $e($heading) . '</h1>' . $hi
            . '<p style="margin:0 0 20px;font-size:16px;line-height:1.5;color:#1d1b1c">' . $e($text) . '</p>'
            . '<p style="margin:0 0 26px"><a href="' . $e($url) . '" style="display:inline-block;background:' . self::GREEN . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 24px;border-radius:10px">Open Staff Calendar</a></p></td></tr>'
            . '<tr><td style="padding:14px 26px 22px;border-top:1px solid #e6e9e4;font-size:12px;color:#6b7067">You get this because you use the Dazu Staff Calendar. You can turn reminders off in “My settings” on the Staff Calendar.</td></tr>'
            . '</table></td></tr></table></body></html>';
        return [$heading, $html];
    }
}
