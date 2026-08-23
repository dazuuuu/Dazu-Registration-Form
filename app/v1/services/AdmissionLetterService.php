<?php
// Generates and stores admission letters for new enrollments (PDF).

class AdmissionLetterService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{ok:bool, error?:string, path?:string, rel_path?:string, html?:string}
     */
    public function generateAndStore(array $registration, array $course): array
    {
        require_once __DIR__ . '/../helpers/site_logo.php';
        require_once __DIR__ . '/../helpers/pdf_render.php';
        require_once __DIR__ . '/../models/SettingModel.php';

        global $pdo;
        $pdo = $this->pdo;

        $schoolName = site_institute_name();
        $logo = site_logo_data_uri() ?: site_logo_url();

        $regNo = (string) ($registration['registration_no'] ?? '');
        if ($regNo === '' && !empty($registration['id'])) {
            $stmt = $this->pdo->prepare('SELECT registration_no FROM registrations WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $registration['id']]);
            $regNo = trim((string) ($stmt->fetchColumn() ?: ''));
        }
        $studentName = (string) ($registration['full_name'] ?? '');
        $courseTitle = (string) ($course['title'] ?? $registration['course_title'] ?? '');
        $studyMode = ucfirst((string) ($registration['study_mode'] ?? 'online'));

        $bodyHtml = $this->documentHtml($schoolName, $logo, $studentName, $courseTitle, $regNo, $studyMode);
        require_once __DIR__ . '/../helpers/document_letterhead.php';
        $html = document_full_html_page('Letter of Admission', $bodyHtml, $schoolName);

        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $dir = public_path('uploads/admission_letters');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create admission letters folder.'];
        }

        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', $regNo) ?: ('reg-' . (int) ($registration['id'] ?? 0));
        $filename = $slug . '.pdf';
        $abs = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!html_to_pdf_file($html, $abs)) {
            return ['ok' => false, 'error' => 'Could not generate admission letter PDF. Run composer install to install Dompdf.'];
        }

        $relPath = 'uploads/admission_letters/' . $filename;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO admission_documents (registration_id, registration_no, student_name, course_title, file_path)
                 VALUES (:rid, :reg_no, :name, :course, :path)'
            );
            $stmt->execute([
                ':rid' => (int) $registration['id'],
                ':reg_no' => $regNo,
                ':name' => $studentName,
                ':course' => $courseTitle,
                ':path' => $relPath,
            ]);
        } catch (Throwable $e) {
            error_log('[AdmissionLetterService] DB save failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'path' => $abs, 'rel_path' => $relPath, 'html' => $html];
    }

    public function documentHtml(
        string $schoolName,
        string $logo,
        string $studentName,
        string $courseTitle,
        string $regNo,
        string $studyMode
    ): string {
        return '<p style="font-size:14px;line-height:1.7;color:#171717;margin:0 0 16px;">Date: _________________________</p>'
            . '<p style="font-size:14px;line-height:1.75;color:#171717;margin:0 0 24px;">'
            . 'Dear <strong>' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p style="font-size:14px;line-height:1.75;color:#171717;margin:0 0 24px;">'
            . 'This is to certify that <strong>' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</strong> has been provisionally admitted to '
            . '<strong>' . htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') . '</strong> (' . htmlspecialchars($studyMode, ENT_QUOTES, 'UTF-8') . ' classes) '
            . 'at <strong>' . htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:24px 0;font-size:14px;border:1px solid #171717;">'
            . '<tr><td style="padding:10px 12px;border-bottom:1px solid #171717;color:#404040;width:40%;">Registration number</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #171717;font-weight:700;">' . htmlspecialchars($regNo, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:10px 12px;border-bottom:1px solid #171717;color:#404040;">Course</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #171717;font-weight:700;">' . htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:10px 12px;border-bottom:1px solid #171717;color:#404040;">Study mode</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #171717;font-weight:700;">' . htmlspecialchars($studyMode, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:10px 12px;color:#404040;">Admission date</td>'
            . '<td style="padding:10px 12px;font-weight:700;">_________________________</td></tr>'
            . '</table>'
            . '<p style="font-size:13px;line-height:1.75;color:#404040;margin:32px 0 48px;">'
            . 'Please present this letter at the admissions office for verification, date confirmation, and official signing.</p>'
            . '<div style="display:flex;justify-content:space-between;gap:48px;margin-top:48px;">'
            . '<div style="flex:1;border-top:1px solid #171717;padding-top:8px;font-size:12px;color:#525252;">Student signature</div>'
            . '<div style="flex:1;border-top:1px solid #171717;padding-top:8px;font-size:12px;color:#525252;">Authorized signatory<br><span style="font-size:11px;">' . htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '</div>';
    }
}
