<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Follow-up web service endpoint (SOAP 1.1 over HTTPS, Order TMS/369/2019 Annex V.A).
 *
 * POST: SOAP requests. GET with ?wsdl: the service definition.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

use local_customreport\sepe\service;
use local_customreport\sepe\wsdl;

$endpoint = (new moodle_url('/local/customreport/ws/seguimiento.php'))->out(false);

if (!is_https() && !get_config('local_customreport', 'ws_allowhttp')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'HTTPS is required.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['wsdl'])) {
    header('Content-Type: text/xml; charset=utf-8');
    echo wsdl::document($endpoint);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, GET');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Use POST with a SOAP 1.1 envelope, or GET ?wsdl.';
    exit;
}

$request = file_get_contents('php://input', false, null, 0, service::MAX_BYTES + 1);
$result = service::handle($request === false ? '' : $request, getremoteaddr() ?? '');
http_response_code($result['http']);
header('Content-Type: text/xml; charset=utf-8');
header('Cache-Control: no-store');
echo $result['body'];
