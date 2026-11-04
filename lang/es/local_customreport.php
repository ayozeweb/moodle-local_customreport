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
 * Spanish strings.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['customreport:exportreports'] = 'Exportar informes de seguimiento';
$string['customreport:manage'] = 'Gestionar la configuración del seguimiento';
$string['customreport:viewallreports'] = 'Ver todos los informes de seguimiento (solo lectura)';
$string['customreport:viewgroupreports'] = 'Ver informes de seguimiento de los grupos propios';
$string['customreport:viewidentitydocuments'] = 'Ver documentos de identidad recibidos por el servicio web';
$string['customreport:viewownreport'] = 'Ver el propio informe de seguimiento';
$string['inactivitydays'] = 'Días sin acceso (alerta)';
$string['inactivitydays_desc'] = 'Número de días sin acceso a partir del cual se avisa al tutor sobre el alumno.';
$string['logretentiondays'] = 'Retención del registro del servicio web (días)';
$string['logretentiondays_desc'] = 'Días que se conservan las peticiones y respuestas SOAP registradas.';
$string['pluginname'] = 'Informes de seguimiento de teleformación';
$string['privacy:metadata'] = 'El plugin todavía no almacena datos personales. Las versiones posteriores guardarán datos agregados de seguimiento, que deben conservarse durante el periodo legal de control.';
$string['sessiongap'] = 'Umbral de corte de sesión';
$string['sessiongap_desc'] = 'Una sesión termina cuando el hueco entre dos eventos del registro supera este valor.';
$string['sessiontail'] = 'Cola de sesión';
$string['sessiontail_desc'] = 'Tiempo que se suma al último evento de cada sesión. El tiempo dedicado es una estimación.';
