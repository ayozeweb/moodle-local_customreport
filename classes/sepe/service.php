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
 * SOAP 1.1 document/literal follow-up service (Order TMS/369/2019, Annex V.A).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Processes SOAP requests with DOMDocument (ext-soap is not required), following ProveedorCentroTFWS.wsdl.
 *
 * Return codes: -2 service not available, -1 unexpected error, 0 correct, 1 centre with actions / action exists /
 * action does not exist (depending on the operation), 2 parameter error (the offending tag goes in ETIQUETA_ERROR).
 */
class service {
    /** @var int Maximum accepted request size in bytes. */
    const MAX_BYTES = 5242880;

    /** @var string[][] Operation => [response wrapper, response root element, handler method]. */
    const OPERATIONS = [
        'crearCentro' => ['crearCentroResponse', 'RESPUESTA_DATOS_CENTRO', 'create_center'],
        'obtenerDatosCentro' => ['obtenerDatosCentroResponse', 'RESPUESTA_DATOS_CENTRO', 'get_center'],
        'crearAccion' => ['crearAccionResponse', 'RESPUESTA_OBT_ACCION', 'create_action'],
        'obtenerAccion' => ['obtenerAccionResponse', 'RESPUESTA_OBT_ACCION', 'get_action'],
        'obtenerListaAcciones' => ['obtenerListaAccionesResponse', 'RESPUESTA_OBT_LISTA_ACCIONES', 'list_actions'],
        'eliminarAccion' => ['eliminarAccionResponse', 'RESPUESTA_ELIMINAR_ACCION', 'delete_action'],
    ];

    /** @var \DOMDocument Response document. */
    private $out;

    /** @var int */
    private $now;

    /**
     * Handle a request.
     *
     * @param string $xml Request body.
     * @param string $remoteaddr Client address (for the log).
     * @param int|null $now Current time (tests).
     * @return array ['http' => int, 'body' => string, 'operation' => string, 'returncode' => int]
     */
    public static function handle(string $xml, string $remoteaddr = '', ?int $now = null): array {
        $service = new self();
        $service->now = $now ?? time();
        $result = $service->process($xml);
        store::log(
            $result['operation'],
            $result['username'],
            $remoteaddr,
            $result['returncode'],
            $xml,
            $result['body'],
            $service->now
        );
        unset($result['username']);
        return $result;
    }

    /**
     * Process a request.
     *
     * @param string $xml
     * @return array
     */
    private function process(string $xml): array {
        $fail = fn(string $op, string $user, string $text, int $rc = -1) => $this->fault($op, $user, $text, $rc);
        if (strlen($xml) > self::MAX_BYTES) {
            return $fail('?', '', 'Request too large');
        }
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $xml)) {
            return $fail('?', '', 'DTD not allowed');
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (
            !$loaded || !$doc->documentElement || $doc->documentElement->namespaceURI !== wsdl::NS_SOAP
                || $doc->documentElement->localName !== 'Envelope'
        ) {
            return $fail('?', '', 'Malformed SOAP envelope');
        }
        $bodies = $doc->getElementsByTagNameNS(wsdl::NS_SOAP, 'Body');
        $request = null;
        if ($bodies->length) {
            foreach ($bodies->item(0)->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $request = $child;
                    break;
                }
            }
        }
        if (!$request || $request->namespaceURI !== wsdl::NS_IMPL || !isset(self::OPERATIONS[$request->localName])) {
            return $fail('?', '', 'Unknown operation');
        }
        $operation = $request->localName;

        $enabled = get_config('local_customreport', 'ws_enabled');
        if ($enabled !== false && !$enabled) {
            return $this->respond($operation, '', -2, null, []);
        }
        $token = security::read_token($doc);
        $cred = security::authenticate($token, $this->now);
        if (!$cred) {
            return $this->fault($operation, $token['username'] ?? '', 'FailedAuthentication', -1, 'wsse:FailedAuthentication');
        }
        try {
            [$code, $label, $nodes] = $this->{self::OPERATIONS[$operation][2]}($request);
        } catch (\Throwable $e) {
            debugging('local_customreport web service: ' . $e->getMessage(), DEBUG_DEVELOPER);
            [$code, $label, $nodes] = [-1, 'ERROR_INESPERADO', []];
        }
        return $this->respond($operation, $cred->username, $code, $label, $nodes);
    }

    /**
     * First child element with the given local name.
     *
     * @param \DOMElement $parent
     * @param string $name
     * @return \DOMElement|null
     */
    private function child(\DOMElement $parent, string $name): ?\DOMElement {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Validate the payload element of a request.
     *
     * @param \DOMElement $request
     * @param string $name
     * @return array [element|null, error label|null]
     */
    private function payload(\DOMElement $request, string $name): array {
        $el = $this->child($request, $name);
        if (!$el || $el->namespaceURI !== wsdl::NS_ENTSAL) {
            return [null, $name];
        }
        return [$el, wsdl::validate($el, 'entsal')];
    }

    /**
     * Operation crearCentro.
     *
     * @param \DOMElement $request
     * @return array
     */
    private function create_center(\DOMElement $request): array {
        global $DB;
        [$el, $error] = $this->payload($request, 'DATOS_IDENTIFICATIVOS');
        if ($error !== null) {
            return [2, $error, $el ? [$el] : []];
        }
        if ($DB->record_exists('local_customreport_wsaction', [])) {
            return [1, null, [$el]];
        }
        store::save_center($el);
        return [0, null, [$el]];
    }

    /**
     * Operation obtenerDatosCentro.
     *
     * @param \DOMElement $request
     * @return array
     */
    private function get_center(\DOMElement $request): array {
        $center = store::get_center();
        if (!$center) {
            return [-1, 'CENTRO_NO_CREADO', []];
        }
        $doc = new \DOMDocument();
        $el = $doc->createElementNS(wsdl::NS_ENTSAL, 'entsal:DATOS_IDENTIFICATIVOS');
        $doc->appendChild($el);
        $add = function (\DOMElement $parent, string $name, string $text) use ($doc) {
            return $parent->appendChild($doc->createElement($name, htmlspecialchars($text, ENT_XML1)));
        };
        $id = $add($el, 'ID_CENTRO', '');
        $add($id, 'ORIGEN_CENTRO', $center->origin);
        $add($id, 'CODIGO_CENTRO', str_pad($center->code, 16));
        $add($el, 'NOMBRE_CENTRO', $center->name);
        $add($el, 'URL_PLATAFORMA', $center->urlplatform);
        $add($el, 'URL_SEGUIMIENTO', $center->urltracking);
        $add($el, 'TELEFONO', $center->phone);
        $add($el, 'EMAIL', $center->email);
        return [0, null, [$el]];
    }

    /**
     * Operation crearAccion.
     *
     * @param \DOMElement $request
     * @return array
     */
    private function create_action(\DOMElement $request): array {
        [$el, $error] = $this->payload($request, 'ACCION_FORMATIVA');
        if ($error !== null) {
            return [2, $error, $el ? [$el] : []];
        }
        $xp = new \DOMXPath($el->ownerDocument);
        $text = fn(string $path) => trim($xp->evaluate("string($path)", $el));
        $semantic = $this->check_action($text);
        if ($semantic !== null) {
            return [2, $semantic, [$el]];
        }
        if (!store::get_center()) {
            return [-1, 'CENTRO_NO_CREADO', [$el]];
        }
        if (store::get_action($text('ID_ACCION/ORIGEN_ACCION'), $text('ID_ACCION/CODIGO_ACCION'))) {
            return [1, null, [$el]];
        }
        $ws = store::save_action($el, $this->now);
        return [0, null, [builder::build($ws, $this->now)->documentElement]];
    }

    /**
     * Semantic checks that the schema cannot express.
     *
     * @param callable $text Function returning the trimmed text of a path of the action.
     * @return string|null Offending tag or null.
     */
    private function check_action(callable $text): ?string {
        if (!in_array((int) $text('SITUACION'), [10, 20, 30, 40, 50], true)) {
            return 'SITUACION';
        }
        if (!in_array($text('TIPO_FINANCIACION'), ['PU', 'PR'], true)) {
            return 'TIPO_FINANCIACION';
        }
        $start = store::parse_date($text('FECHA_INICIO'));
        $end = store::parse_date($text('FECHA_FIN'));
        if (!$start) {
            return 'FECHA_INICIO';
        }
        if (!$end || $end < $start) {
            return 'FECHA_FIN';
        }
        return null;
    }

    /**
     * Operation obtenerAccion.
     *
     * @param \DOMElement $request
     * @return array
     */
    private function get_action(\DOMElement $request): array {
        [$el, $error] = $this->payload($request, 'ID_ACCION');
        if ($error !== null) {
            return [2, $error, []];
        }
        $xp = new \DOMXPath($el->ownerDocument);
        $ws = store::get_action($xp->evaluate('string(ORIGEN_ACCION)', $el), $xp->evaluate('string(CODIGO_ACCION)', $el));
        if (!$ws) {
            return [1, null, []];
        }
        return [0, null, [builder::build($ws, $this->now)->documentElement]];
    }

    /**
     * Operation obtenerListaAcciones.
     *
     * @param \DOMElement $request
     * @return array
     */
    private function list_actions(\DOMElement $request): array {
        $doc = new \DOMDocument();
        $nodes = [];
        foreach (store::started_actions($this->now) as $ws) {
            $id = $doc->createElementNS(wsdl::NS_ENTSAL, 'entsal:ID_ACCION');
            $id->appendChild($doc->createElement('ORIGEN_ACCION', $ws->origin));
            $id->appendChild($doc->createElement('CODIGO_ACCION', htmlspecialchars(str_pad($ws->code, 30), ENT_XML1)));
            $doc->appendChild($id);
            $nodes[] = $id;
            $doc = new \DOMDocument();
        }
        return [0, null, $nodes];
    }

    /**
     * Operation eliminarAccion.
     *
     * @param \DOMElement $request
     * @return array
     */
    private function delete_action(\DOMElement $request): array {
        [$el, $error] = $this->payload($request, 'ID_ACCION');
        if ($error !== null) {
            return [2, $error, []];
        }
        $xp = new \DOMXPath($el->ownerDocument);
        $ws = store::get_action($xp->evaluate('string(ORIGEN_ACCION)', $el), $xp->evaluate('string(CODIGO_ACCION)', $el));
        if (!$ws) {
            return [1, null, []];
        }
        if (!lifecycle::may_delete((int) $ws->situation, (int) $ws->startdate, (int) $ws->enddate, $this->now)) {
            return [-1, 'ACCION_EN_SEGUIMIENTO', []];
        }
        store::delete_action((int) $ws->id);
        return [0, null, []];
    }

    /**
     * Build a normal response.
     *
     * @param string $operation
     * @param string $username
     * @param int $code
     * @param string|null $label
     * @param \DOMNode[] $nodes Elements to append after ETIQUETA_ERROR.
     * @return array
     */
    private function respond(string $operation, string $username, int $code, ?string $label, array $nodes): array {
        [$wrapper, $rootname] = array_slice(self::OPERATIONS[$operation], 0, 2);
        $this->out = new \DOMDocument('1.0', 'UTF-8');
        $this->out->formatOutput = true;
        $env = $this->out->createElementNS(wsdl::NS_SOAP, 'soapenv:Envelope');
        $env->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $env->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $this->out->appendChild($env);
        $env->appendChild($this->out->createElementNS(wsdl::NS_SOAP, 'soapenv:Header'));
        $body = $env->appendChild($this->out->createElementNS(wsdl::NS_SOAP, 'soapenv:Body'));
        $resp = $body->appendChild($this->out->createElementNS(wsdl::NS_IMPL, 'impl:' . $wrapper));
        $root = $resp->appendChild($this->out->createElementNS(wsdl::NS_SALIDA, 'salida:' . $rootname));
        $root->appendChild($this->out->createElement('CODIGO_RETORNO', (string) $code));
        $error = $root->appendChild($this->out->createElement('ETIQUETA_ERROR'));
        if ($label === null) {
            $error->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:nil', 'true');
        } else {
            // The official schema (mensaje_error) requires exactly 250 characters.
            $error->appendChild($this->out->createTextNode(str_pad(\core_text::substr($label, 0, 250), 250)));
        }
        foreach ($nodes as $node) {
            $root->appendChild($this->out->importNode($node, true));
        }
        return ['http' => 200, 'body' => $this->out->saveXML(), 'operation' => $operation, 'returncode' => $code,
            'username' => $username];
    }

    /**
     * Build a SOAP 1.1 fault.
     *
     * @param string $operation
     * @param string $username
     * @param string $text
     * @param int $returncode
     * @param string $faultcode
     * @return array
     */
    private function fault(
        string $operation,
        string $username,
        string $text,
        int $returncode = -1,
        string $faultcode = 'soapenv:Client'
    ): array {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $env = $doc->createElementNS(wsdl::NS_SOAP, 'soapenv:Envelope');
        $doc->appendChild($env);
        $body = $env->appendChild($doc->createElementNS(wsdl::NS_SOAP, 'soapenv:Body'));
        $fault = $body->appendChild($doc->createElementNS(wsdl::NS_SOAP, 'soapenv:Fault'));
        $fault->appendChild($doc->createElement('faultcode', $faultcode));
        $fault->appendChild($doc->createElement('faultstring', htmlspecialchars($text, ENT_XML1)));
        return ['http' => 500, 'body' => $doc->saveXML(), 'operation' => $operation, 'returncode' => $returncode,
            'username' => $username];
    }
}
