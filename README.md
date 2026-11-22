# «Recursos Humanos»

Aplicación de Nextcloud 34 para APS Conecta Gestión: el registro oficial del establecimiento para el feriado legal, los
días administrativos y los demás permisos de su personal, según la Ley 19.378.

## Qué es

Cada persona pide sus días y ve su saldo y lo disponible. Su jefatura directa decide por delegación del Director/a, y
Recursos Humanos registra: solo entonces cambia el saldo, con un movimiento fechado que nunca se borra. La app mantiene
una planilla de respaldo en Excel, un comprobante en PDF por cada permiso registrado y una API versionada (v1) para que
otras aplicaciones se conecten. La interfaz está en español; todo lo demás, en inglés. Licencia AGPL-3.0-or-later.

**Estado:** en construcción. Hoy abre en «Mis días», vacío; Recursos Humanos completa las fichas en «Personal», y
«Nueva solicitud» cuenta los días hábiles de un rango con los días feriados nacionales, aunque todavía no envía.
La especificación es [#1](https://github.com/APS-Conecta/recursos-humanos/issues/1), y el trabajo pendiente está en sus
subtareas. La primera versión trae los permisos; las calificaciones y la carrera funcionaria vienen después.

## Documentos

- [`CONTEXT.md`](CONTEXT.md): el vocabulario (solicitud, saldo, disponible, movimiento, jefatura, subrogante), en
  inglés con su nombre en español.
- [`docs/adr/`](docs/adr/): las decisiones.
- [`docs/research/aps-hr-law.md`](docs/research/aps-hr-law.md): las reglas de la Ley 19.378 y sus fuentes.
- [`openapi.json`](openapi.json): la API OCS, generada por `make openapi` desde el controlador, nunca a mano.
- [`CHANGELOG.md`](CHANGELOG.md): qué cambió y cuándo.
- La maqueta aprobada está enlazada desde la especificación.

## Desarrollo

El arnés es el de «IA», que es el de Estadística. Con Docker, Node 24, `make` y
[aps-common](https://github.com/APS-Conecta/aps-common) clonado junto a este repositorio:

- `npm ci && npm run build`: el paquete de `js/`, que no se versiona.
- `make aps-drift`: prueba que las copias de aps-common (`vendor/aps/common/src`, `src/aps`) son iguales al paquete.
- `make test`: las compuertas estáticas, en un contenedor PHP sin Nextcloud.
- `make deps && make instance-up`: una instancia desechable de Nextcloud 34 con PostgreSQL 18 en
  http://localhost:8094 (admin / admin), con los grupos de cargo que crea la suite.
- `make test-seam`: la batería que ve la app como la ve una persona o un operador: su API OCS y occ.
- `make openapi`: regenera `openapi.json`.
- `make instance-clean`: borra la instancia y su base de datos.
