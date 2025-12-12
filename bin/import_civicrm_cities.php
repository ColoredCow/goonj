#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Import state and city data into CiviCRM tables from a CSV export of the Google Sheet.
 *
 * Usage:
 *   php bin/import_civicrm_cities.php [path/to/input.csv]
 *   Defaults to bin/Newcitydata.csv when no path is provided.
 *
 * The CSV must contain "State Name" and "City Name" headers (case-insensitive).
 * An optional "Country Name" column can be included; when omitted, the importer
 * assumes "India". Blank cells are treated as "carry forward" values, matching
 * how Google Sheets exports merged cells.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

if (!defined('SHORTINIT')) {
    define('SHORTINIT', true);
}

require_once __DIR__ . '/../wp-config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Attempt to include CiviCRM settings so we can connect using its DSN when available.
 */
$potentialSettings = [];
if (defined('CIVICRM_SETTINGS_PATH')) {
    $potentialSettings[] = rtrim(CIVICRM_SETTINGS_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'civicrm.settings.php';
}
if (defined('WP_CONTENT_DIR')) {
    $potentialSettings[] = WP_CONTENT_DIR . '/uploads/civicrm/civicrm.settings.php';
}
$potentialSettings[] = __DIR__ . '/../wp-content/uploads/civicrm/civicrm.settings.php';

foreach ($potentialSettings as $settingsPath) {
    if (is_readable($settingsPath)) {
        require_once $settingsPath;
        break;
    }
}

$resolveDsn = static function (string $dsn): array {
    $parts = parse_url($dsn);
    if ($parts === false) {
        throw new RuntimeException("Unable to parse CIVICRM_DSN value: {$dsn}");
    }

    $host = $parts['host'] ?? 'localhost';
    $user = $parts['user'] ?? '';
    $password = $parts['pass'] ?? '';
    $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
    $port = isset($parts['port']) ? (int) $parts['port'] : (int) ini_get('mysqli.default_port');
    $socket = null;

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        if (isset($query['socket'])) {
            $socket = $query['socket'];
        }
    }

    return [
        'host' => $host,
        'user' => $user,
        'password' => $password,
        'database' => $database,
        'port' => $port,
        'socket' => $socket,
    ];
};

$defaultPath = __DIR__ . '/Newcitydata.csv';
$inputPath = $argv[1] ?? $defaultPath;

if (!is_readable($inputPath)) {
    if (isset($argv[1])) {
        fwrite(STDERR, "Input file '{$inputPath}' is not readable.\n");
    } else {
        fwrite(
            STDERR,
            "Default input file '{$inputPath}' is not readable. " .
            "Either place the CSV there or provide a path: php bin/import_civicrm_cities.php /path/to/input.csv\n"
        );
    }
    exit(1);
}

$dbConfig = null;
if (defined('CIVICRM_DSN')) {
    try {
        $dbConfig = $resolveDsn(CIVICRM_DSN);
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\nFalling back to WordPress database credentials.\n");
    }
}

if ($dbConfig !== null) {
    $db = @new mysqli(
        $dbConfig['host'],
        $dbConfig['user'],
        $dbConfig['password'],
        $dbConfig['database'],
        $dbConfig['port'] > 0 ? $dbConfig['port'] : ini_get('mysqli.default_port'),
        $dbConfig['socket']
    );
} else {
    $db = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
}
if ($db->connect_errno) {
    fwrite(
        STDERR,
        sprintf(
            "Database connection failed (%d): %s\n",
            $db->connect_errno,
            $db->connect_error
        )
    );
    exit(1);
}
$db->set_charset('utf8mb4');

$handle = fopen($inputPath, 'r');
if ($handle === false) {
    fwrite(STDERR, "Unable to open '{$inputPath}' for reading.\n");
    exit(1);
}

$headerRow = fgetcsv($handle);
if ($headerRow === false) {
    fwrite(STDERR, "Input file '{$inputPath}' is empty.\n");
    exit(1);
}

$normaliseHeader = static function (string $value): string {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', $value));
};

$stripBom = static function (string $value): string {
    if (substr($value, 0, 3) === "\xEF\xBB\xBF") {
        return substr($value, 3);
    }
    return $value;
};

$headerMap = [];
foreach ($headerRow as $index => $value) {
    $clean = $stripBom(trim($value));
    $headerMap[$normaliseHeader($clean)] = $index;
}

$countryIndex = $headerMap['countryname'] ?? null;
$stateIndex = $headerMap['statename'] ?? null;
$cityIndex = $headerMap['cityname'] ?? null;

if ($stateIndex === null || $cityIndex === null) {
    fwrite(STDERR, "Input file must contain the headers 'State Name' and 'City Name'.\n");
    exit(1);
}

$selectCountryStmt = $db->prepare('SELECT id FROM civicrm_country WHERE name = ?');
$selectStateStmt = $db->prepare('SELECT id FROM civicrm_state_province WHERE name = ? AND country_id = ?');
$insertStateStmt = $db->prepare('INSERT INTO civicrm_state_province (name, country_id, is_active) VALUES (?, ?, 1)');
$selectCityStmt = $db->prepare('SELECT id FROM civicrm_city WHERE name = ? AND state_province_id = ?');
$insertCityStmt = $db->prepare('INSERT INTO civicrm_city (name, state_province_id) VALUES (?, ?)');

$countries = [];
$states = [];
$insertedStates = 0;
$insertedCities = 0;
$skippedCities = 0;
$skippedCityDetails = [];
$rowNumber = 1; // 1-based to match human-readable line numbers.
$defaultCountryName = $countryIndex === null ? 'India' : null;
$currentCountry = $defaultCountryName;
$currentState = null;
$toLower = static function (string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$db->begin_transaction();

try {
    while (($row = fgetcsv($handle)) !== false) {
        ++$rowNumber;

        $countryName = '';
        if ($countryIndex !== null && array_key_exists($countryIndex, $row)) {
            $countryName = trim((string) $row[$countryIndex]);
        }
        $stateName = isset($row[$stateIndex]) ? trim((string) $row[$stateIndex]) : '';
        $cityName = isset($row[$cityIndex]) ? trim((string) $row[$cityIndex]) : '';

        if ($countryIndex === null) {
            $currentCountry = $defaultCountryName;
        } elseif ($countryName !== '') {
            $currentCountry = $countryName;
        }

        if ($stateName !== '') {
            $currentState = $stateName;
        }

        if ($currentCountry === null || $currentState === null) {
            fwrite(STDERR, "Skipping row {$rowNumber}: missing country or state context.\n");
            continue;
        }

        if ($cityName === '') {
            continue; // No city to import on this row.
        }

        $countryKey = $currentCountry;

        if (!isset($countries[$countryKey])) {
            $selectCountryStmt->bind_param('s', $currentCountry);
            $selectCountryStmt->execute();
            $selectCountryStmt->bind_result($countryId);
            if ($selectCountryStmt->fetch()) {
                $countries[$countryKey] = $countryId;
            } else {
                throw new RuntimeException("Country '{$currentCountry}' not found in civicrm_country (row {$rowNumber}).");
            }
            $selectCountryStmt->free_result();
        }

        $countryId = $countries[$countryKey];
        $stateKey = $countryId . '|' . $toLower($currentState);

        if (!isset($states[$stateKey])) {
            $selectStateStmt->bind_param('si', $currentState, $countryId);
            $selectStateStmt->execute();
            $selectStateStmt->bind_result($stateId);
            $foundState = false;
            if ($selectStateStmt->fetch()) {
                $states[$stateKey] = $stateId;
                $foundState = true;
            }
            $selectStateStmt->free_result();
            if (!$foundState) {
                $insertStateStmt->bind_param('si', $currentState, $countryId);
                $insertStateStmt->execute();
                $states[$stateKey] = $db->insert_id;
                ++$insertedStates;
            }
        }

        $stateId = $states[$stateKey];

        $selectCityStmt->bind_param('si', $cityName, $stateId);
        $selectCityStmt->execute();
        $selectCityStmt->bind_result($cityId);
        if ($selectCityStmt->fetch()) {
            ++$skippedCities;
            $skippedCityDetails[] = sprintf(
                "%s / %s (row %d)",
                $currentState,
                $cityName,
                $rowNumber
            );
            $selectCityStmt->free_result();
            continue;
        }
        $selectCityStmt->free_result();

        $insertCityStmt->bind_param('si', $cityName, $stateId);
        $insertCityStmt->execute();
        ++$insertedCities;
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    fclose($handle);
    fwrite(STDERR, "Import failed: {$e->getMessage()}\n");
    exit(1);
}

fclose($handle);

printf(
    "Import complete. States inserted: %d, Cities inserted: %d, Cities skipped (already existed): %d\n",
    $insertedStates,
    $insertedCities,
    $skippedCities
);

if (!empty($skippedCityDetails)) {
    echo "Duplicate city entries (state / city):\n";
    foreach ($skippedCityDetails as $detail) {
        echo " - {$detail}\n";
    }
}

$selectCountryStmt->close();
$selectStateStmt->close();
$insertStateStmt->close();
$selectCityStmt->close();
$insertCityStmt->close();
$db->close();
