# Servicio web de seguimiento (Anexo V.A)

Endpoint: `/local/customreport/ws/seguimiento.php` (POST SOAP 1.1 document/literal; `GET ?wsdl` devuelve el WSDL). Exige HTTPS (ajuste `ws_allowhttp` solo para desarrollo). Autenticación WS-Security UsernameToken con `wsse:PasswordText`; las credenciales se crean en *Servicio web de seguimiento* (`wsadmin.php`).

## Origen del WSDL
`docs/normativa/ProveedorCentroTFWS.wsdl` es en realidad una página HTML de GitHub guardada (vista de `studiolxd/moodle-local_sepe`, GPL). El WSDL real se recuperó de ella sin cambios y está en `ws/ProveedorCentroTFWS.wsdl`; los esquemas están en `ws/schema/`. El original de `docs/normativa/` no se ha modificado.

## Desviaciones documentadas
- Los patrones del WSDL usan `^…$`, que en XSD son literales y rechazarían cualquier URL, teléfono o correo válido. `ws/schema/entsal.xsd` es una copia con esos anclajes retirados.
- Los nombres reales son `CODIGO_RETORNO` y `RESPUESTA_*` (el texto del modelo de datos habla de `<codigoRetorno>`). Se sigue el WSDL.
- `ETIQUETA_ERROR` se rellena a 250 caracteres (facet `length` del WSDL).
- Códigos: -2 servicio desactivado; -1 error inesperado (sin centro, acción en seguimiento no eliminable); 0 correcto; 1 centro con acciones / acción existente / inexistente; 2 parámetro erróneo (tag en `ETIQUETA_ERROR`).
- `obtenerListaAcciones` devuelve las acciones iniciadas (situación 30/40 o fecha de inicio pasada).
- `eliminarAccion` solo se permite antes del inicio (acciones de prueba) o 30 días naturales después del fin.
- Tutores, uso, participantes y resultados se informan desde el inicio y solo si la acción está vinculada a una acción de Moodle (por código o manualmente en `wsadmin.php`).
- Franjas horarias: mañana 7–15, tarde 15–23, noche 23–7 (hora del servidor), redondeo de horas con 0,5 hacia abajo. Puntuación final nunca inferior a 5 ni a la prueba final (MD B12.1.4.6.3).
