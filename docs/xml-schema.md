# Esquema XML del informe de seguimiento

El informe de la visita virtual (Orden TMS/369/2019, Anexo V.B) se exporta a XML con un exportador propio
(`\local_customreport\export\xml`), porque Report builder no ofrece este formato. El esquema formal está en
[`followup.xsd`](followup.xsd) (espacio de nombres `urn:moodle:local_customreport:followup:1`).

## Estructura

```xml
<followup version="1" generated="2026-11-04T10:00:00Z" profile="sepe_sce_tms369">
  <action>
    origin, code, centercode, areacode, specialtycode, name, authority, modality, situation, hours,
    startdate type="real|planned", enddate type="real|planned"
  </action>
  <filters> from, to (YYYY-MM-DD, vacío = sin límite), nodeid (0 = toda la acción) </filters>
  <summary> learners, started(+percent), completed(+percent), dropped(+percent), totalseconds, avgseconds,
            accesses, participations, avgscore, passall(+percent), passfct(+percent) </summary>
  <nodes>  <node id type="MF|UF|MP"> code, name, hours, passed, passedpercent, seconds, avgseconds </node> </nodes>
  <areas>  <area component="mod_forum"> accesses, accessespercent, seconds, participations </area> </areas>
  <learners>
    <learner id status="notstarted|started|completed|dropped">
      fullname, accesses, participations, seconds, firstaccess, lastaccess, total, done, percent,
      processavg, final1, final2, score, call, passed
    </learner>
  </learners>
</followup>
```

## Reglas

- Los tiempos están en segundos y son una **estimación** (ver README, «Cálculo del tiempo»).
- Las fechas de la acción llevan `type="real"` si ya han pasado (más de 7 días antes de la consulta) o `planned` si no.
- Las notas están en escala 0–10. `score` es la puntuación final del módulo
  (`peso_proceso × media de proceso + peso_final × prueba final`) solo cuando la prueba final alcanza la nota
  mínima del perfil normativo; `call` indica la convocatoria (1 o 2).
- Los elementos sin dato se emiten vacíos.
- `percent`/`…percent` están en el intervalo 0–100 con un decimal.
- El documento se valida con `followup.xsd` en las pruebas automáticas.
