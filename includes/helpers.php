<?php
declare(strict_types=1);

function e(string|null|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return BASE_URL === '' ? '/' : BASE_URL . '/';
    }

    return (BASE_URL === '' ? '' : BASE_URL) . '/' . $path;
}

function redirect(string $path): never
{
    $path = trim($path);

    $url = str_starts_with($path, 'http://')
        || str_starts_with($path, 'https://')
        || str_starts_with($path, '/')
        ? $path
        : base_url($path);

    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

function render_flash_messages(): void
{
    $classMap = [
        'success' => 'success',
        'danger' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
    ];

    foreach (pull_flashes() as $message) {
        $type = $classMap[$message['type'] ?? 'info'] ?? 'info';
        echo '<div class="alert alert-' . e($type) . ' shadow-sm border-0" role="alert">';
        echo e((string) ($message['message'] ?? ''));
        echo '</div>';
    }
}

function money(float|int|string|null $amount): string
{
    return 'PHP ' . number_format((float) $amount, 2);
}

function format_date(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) {
        return 'Not set';
    }

    $timestamp = strtotime($date);

    return $timestamp ? date($format, $timestamp) : 'Invalid date';
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Active' => 'text-bg-success',
        'Disposed' => 'text-bg-secondary',
        'Fully Depreciated' => 'text-bg-warning',
        default => 'text-bg-light',
    };
}

function condition_badge_class(string $condition): string
{
    return match ($condition) {
        'Healthy' => 'text-bg-success',
        'Monitor' => 'text-bg-warning',
        'Critical' => 'text-bg-danger',
        default => 'text-bg-light',
    };
}

function role_badge_class(string $role): string
{
    return match ($role) {
        'Admin' => 'text-bg-primary',
        'Accounting Staff' => 'text-bg-warning',
        'Auditor' => 'text-bg-info',
        default => 'text-bg-light',
    };
}

function active_path(string|array $needle): bool
{
    $scriptName = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
    $needles = is_array($needle) ? $needle : [$needle];

    foreach ($needles as $candidate) {
        if (str_ends_with($scriptName, (string) $candidate)) {
            return true;
        }
    }

    return false;
}

function request_value(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function organization_options(): array
{
    return defined('ORGANIZATION_OPTIONS') && is_array(ORGANIZATION_OPTIONS)
        ? ORGANIZATION_OPTIONS
        : [];
}

function normalize_organization_code(mixed $code): string
{
    $candidate = strtolower(trim((string) $code));
    $options = organization_options();

    if (isset($options[$candidate])) {
        return $candidate;
    }

    if (defined('DEFAULT_ORGANIZATION_CODE')) {
        $fallback = strtolower(trim((string) DEFAULT_ORGANIZATION_CODE));
        if (isset($options[$fallback])) {
            return $fallback;
        }
    }

    return array_key_first($options) ?? 'ntrprising';
}

function current_organization_code(): string
{
    return normalize_organization_code(
        $_SESSION['organization_code']
            ?? $_COOKIE['ppe-organization']
            ?? DEFAULT_ORGANIZATION_CODE
            ?? 'ntrprising'
    );
}

function current_organization(): array
{
    $options = organization_options();
    $code = current_organization_code();

    return $options[$code] ?? [
        'label' => strtoupper($code),
        'short_label' => strtoupper(substr($code, 0, 2)),
        'tagline' => APP_NAME,
    ];
}

function set_current_organization(mixed $code): void
{
    $normalized = normalize_organization_code($code);
    $_SESSION['organization_code'] = $normalized;
    $_COOKIE['ppe-organization'] = $normalized;

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        $cookiePath = BASE_URL === '' ? '/' : BASE_URL;
        setcookie('ppe-organization', $normalized, [
            'expires' => time() + 31536000,
            'path' => $cookiePath,
            'samesite' => 'Lax',
        ]);
    }
}

function current_route(array $overrides = [], array $remove = []): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $normalizedBase = str_replace('\\', '/', BASE_URL);
    $relativePath = ltrim($scriptName, '/');

    if ($normalizedBase !== '') {
        $basePrefix = ltrim($normalizedBase, '/') . '/';
        if (str_starts_with($relativePath, $basePrefix)) {
            $relativePath = substr($relativePath, strlen($basePrefix));
        }
    }

    $params = $_GET;

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }

        $params[$key] = $value;
    }

    $url = base_url($relativePath);

    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function handle_organization_switch_request(): void
{
    if (!isset($_SESSION['organization_code']) && !isset($_COOKIE['ppe-organization'])) {
        set_current_organization(DEFAULT_ORGANIZATION_CODE ?? 'ntrprising');
    }

    $requestedCode = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['organization_switch'])) {
        $requestedCode = $_POST['organization_code'] ?? null;
    } elseif (isset($_GET['organization_switch'])) {
        $requestedCode = $_GET['organization_code'] ?? null;
    }

    if ($requestedCode === null) {
        return;
    }

    set_current_organization($requestedCode);
    redirect(current_route([], ['organization_switch', 'organization_code']));
}

function selected_if(mixed $left, mixed $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

function checked_if(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function pluralize(int $count, string $singular, ?string $plural = null): string
{
    return $count === 1 ? $singular : ($plural ?? $singular . 's');
}

function excerpt(string $text, int $limit = 180): string
{
    $text = trim($text);

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '...');
    }

    return strlen($text) > $limit ? substr($text, 0, max(0, $limit - 3)) . '...' : $text;
}

function download_csv(string $filename, array $headerRow, array $rows): never
{
    $safeFilename = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($filename)) ?: 'export.csv';

    if (!str_ends_with(strtolower($safeFilename), '.csv')) {
        $safeFilename .= '.csv';
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $stream = fopen('php://output', 'wb');

    if ($stream === false) {
        throw new RuntimeException('Unable to open the CSV output stream.');
    }

    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headerRow);

    foreach ($rows as $row) {
        $values = array_map(
            static fn (mixed $value): string => $value === null ? '' : (string) $value,
            $row
        );
        fputcsv($stream, $values);
    }

    fclose($stream);
    exit;
}

function download_excel(string $filename, array $headerRow, array $rows, string $sheetName = 'Export'): never
{
    $safeFilename = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($filename)) ?: 'export.xlsx';

    if (!str_ends_with(strtolower($safeFilename), '.xlsx')) {
        $safeFilename .= '.xlsx';
    }

    $workbookBinary = build_excel_workbook_binary($headerRow, $rows, $sheetName);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($workbookBinary));
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $workbookBinary;
    exit;
}

function build_excel_workbook_binary(array $headerRow, array $rows, string $sheetName = 'Export'): string
{
    $sheetTitle = sanitize_excel_sheet_name($sheetName);
    $sheetXml = build_excel_sheet_xml(array_merge([$headerRow], $rows));
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>PPE Lapsing System</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . excel_xml_escape($sheetTitle) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company></Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>1.0</AppVersion>'
            . '</Properties>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . excel_xml_escape($sheetTitle) . '</dc:title>'
            . '<dc:creator>PPE Lapsing System</dc:creator>'
            . '<cp:lastModifiedBy>PPE Lapsing System</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified>'
            . '</cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . excel_xml_escape($sheetTitle) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    return build_simple_zip_archive($files);
}

function sanitize_excel_sheet_name(string $sheetName): string
{
    $cleanName = trim(preg_replace('/[\\\\\\/*?:\\[\\]]+/', ' ', $sheetName) ?? '');
    $cleanName = preg_replace('/\\s+/', ' ', $cleanName) ?? '';

    if ($cleanName === '') {
        return 'Export';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($cleanName, 0, 31);
    }

    return substr($cleanName, 0, 31);
}

function build_excel_sheet_xml(array $rows): string
{
    $rowCount = max(1, count($rows));
    $maxColumns = 1;

    foreach ($rows as $row) {
        $maxColumns = max($maxColumns, is_array($row) ? count($row) : 0);
    }

    $dimension = 'A1:' . excel_column_name($maxColumns) . $rowCount;
    $sheetDataXml = '';

    foreach ($rows as $rowIndex => $row) {
        if (!is_array($row)) {
            continue;
        }

        $cellsXml = '';
        $styleIndex = $rowIndex === 0 ? 1 : 0;

        foreach (array_values($row) as $columnIndex => $value) {
            $cellXml = build_excel_sheet_cell_xml($columnIndex + 1, $rowIndex + 1, $value, $styleIndex);

            if ($cellXml !== '') {
                $cellsXml .= $cellXml;
            }
        }

        if ($cellsXml === '') {
            continue;
        }

        $sheetDataXml .= '<row r="' . ($rowIndex + 1) . '">' . $cellsXml . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<sheetData>' . $sheetDataXml . '</sheetData>'
        . '</worksheet>';
}

function build_excel_sheet_cell_xml(int $columnIndex, int $rowIndex, mixed $value, int $styleIndex = 0): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $reference = excel_column_name($columnIndex) . $rowIndex;
    $styleAttribute = $styleIndex > 0 ? ' s="' . $styleIndex . '"' : '';

    if (excel_value_is_numeric($value)) {
        return '<c r="' . $reference . '"' . $styleAttribute . '><v>' . (string) $value . '</v></c>';
    }

    return '<c r="' . $reference . '"' . $styleAttribute . ' t="inlineStr"><is><t xml:space="preserve">'
        . excel_xml_escape((string) $value)
        . '</t></is></c>';
}

function excel_value_is_numeric(mixed $value): bool
{
    if (is_int($value) || is_float($value)) {
        return true;
    }

    if (!is_string($value)) {
        return false;
    }

    $trimmed = trim($value);

    return $trimmed !== ''
        && preg_match('/^-?(?:\\d+|\\d*\\.\\d+)$/', $trimmed) === 1;
}

function excel_column_name(int $columnIndex): string
{
    $name = '';

    while ($columnIndex > 0) {
        $columnIndex--;
        $name = chr(65 + ($columnIndex % 26)) . $name;
        $columnIndex = intdiv($columnIndex, 26);
    }

    return $name;
}

function excel_xml_escape(string $value): string
{
    $value = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/', '', $value) ?? '';

    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function build_simple_zip_archive(array $files): string
{
    $archive = '';
    $centralDirectory = '';
    $offset = 0;
    [$dosTime, $dosDate] = excel_zip_dos_timestamp();

    foreach ($files as $name => $contents) {
        $normalizedName = ltrim(str_replace('\\', '/', (string) $name), '/');
        $contents = (string) $contents;
        $useCompression = function_exists('gzdeflate');
        $compressionMethod = $useCompression ? 8 : 0;
        $data = $useCompression ? gzdeflate($contents) : $contents;
        $crc = crc32($contents);

        if ($crc < 0) {
            $crc += 4294967296;
        }

        $compressedSize = strlen($data);
        $uncompressedSize = strlen($contents);
        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            $compressionMethod,
            $dosTime,
            $dosDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            strlen($normalizedName),
            0
        );

        $archive .= $localHeader . $normalizedName . $data;

        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            $compressionMethod,
            $dosTime,
            $dosDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            strlen($normalizedName),
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $normalizedName;

        $offset += strlen($localHeader) + strlen($normalizedName) + $compressedSize;
    }

    $endOfCentralDirectory = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        count($files),
        count($files),
        strlen($centralDirectory),
        strlen($archive),
        0
    );

    return $archive . $centralDirectory . $endOfCentralDirectory;
}

function excel_zip_dos_timestamp(?int $timestamp = null): array
{
    $date = getdate($timestamp ?? time());
    $year = max(1980, (int) $date['year']);
    $dosTime = ((int) $date['hours'] << 11) | ((int) $date['minutes'] << 5) | intdiv((int) $date['seconds'], 2);
    $dosDate = (($year - 1980) << 9) | ((int) $date['mon'] << 5) | (int) $date['mday'];

    return [$dosTime, $dosDate];
}
