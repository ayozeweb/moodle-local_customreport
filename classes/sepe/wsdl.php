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
 * WSDL and schema helpers.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Namespaces, WSDL delivery and validation of messages against the official schemas.
 */
class wsdl {
    /** @var string Operations namespace. */
    const NS_IMPL = 'http://impl.ws.application.proveedorcentro.meyss.spee.es';

    /** @var string Shared data types namespace. */
    const NS_ENTSAL = 'http://entsal.bean.domain.common.proveedorcentro.meyss.spee.es';

    /** @var string Response types namespace. */
    const NS_SALIDA = 'http://salida.bean.domain.common.proveedorcentro.meyss.spee.es';

    /** @var string SOAP 1.1 envelope namespace. */
    const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * Directory with the WSDL and the schemas.
     *
     * @return string
     */
    public static function dir(): string {
        global $CFG;
        return $CFG->dirroot . '/local/customreport/ws';
    }

    /**
     * The WSDL with the endpoint URL filled in.
     *
     * @param string $endpoint
     * @return string
     */
    public static function document(string $endpoint): string {
        return str_replace(
            '@@ENDPOINTURL@@',
            htmlspecialchars($endpoint, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            file_get_contents(self::dir() . '/ProveedorCentroTFWS.wsdl')
        );
    }

    /**
     * Validate an element against entsal.xsd (requests) or salida.xsd (responses).
     *
     * @param \DOMElement $element Root element (ACCION_FORMATIVA, DATOS_IDENTIFICATIVOS, ID_ACCION, RESPUESTA_*).
     * @param string $schema 'entsal' or 'salida'.
     * @return string|null Null when valid; otherwise the local name of the first offending element.
     */
    public static function validate(\DOMElement $element, string $schema = 'entsal'): ?string {
        $doc = new \DOMDocument();
        $doc->appendChild($doc->importNode($element, true));
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $doc->schemaValidate(self::dir() . '/schema/' . $schema . '.xsd');
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($valid) {
            return null;
        }
        foreach ($errors as $error) {
            if (preg_match("/Element '(?:\{[^}]*\})?([^']+)'/", $error->message, $m)) {
                return $m[1];
            }
        }
        return $element->localName;
    }
}
