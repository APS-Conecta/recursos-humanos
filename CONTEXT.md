# «Recursos Humanos»

The establishment's official record of its staff's leave, ratings and career scores, kept under Ley 19.378. These terms
keep the meaning gestion-aio's `CONTEXT.md` gives them: Establishment, Person and Cargo.

## Language

**«Recursos Humanos»**:
The suite's own app where staff ask for leave and see their days, and where the establishment keeps the official
record of leave, ratings and career scores.
_Avoid_: RRHH app, portal del funcionario

**HR officer** (encargado/a de Recursos Humanos):
A person whose Cargo is Recursos Humanos; keeps the record and registers what was approved.
_Avoid_: admin, administrador

**Director** (Director/a):
The establishment's head, who holds the legal power to grant leave and may decide any Request.

**Delegation** (delegación):
The Director's standing act that lets the Jefaturas decide Requests on the Director's behalf.

**Jefatura** (jefatura directa):
The one person the HR officer sets, for each person, to decide that person's Requests; a person without one goes
to the Director.
_Avoid_: jefe, supervisor, aprobador, superior

**Overview group** (grupo con vista general):
A group an administrator picks whose members see every person's days, Requests and Balances.
_Avoid_: admin group, supervisores

**Stand-in** (subrogante):
The person who decides in place of an absent Jefatura for a date range the HR officer sets; the Subdirector stands
in for the Director, marked «(S)».
_Avoid_: reemplazo (in Ley 19.378 it names a contract), suplente

### Leave

**Leave type** (tipo de permiso):
A kind of leave with its own days and rules: a Legal leave type comes with the app, a Local leave type is added by
the HR officer.
_Avoid_: categoría, motivo

**Legal leave type** (permiso legal):
A Leave type the law defines, with the law's days and rules: Annual leave, Administrative day, unpaid leave,
marriage or civil union, bereavement, birth, training, overtime compensation.

**Local leave type** (permiso local):
A Leave type the establishment grants on its own terms, such as a birthday day by agreement.
_Avoid_: beneficio, permiso especial

**Years of service** (años de servicio):
The public-sector service recognized for a person, counted from their start date plus prior service; it sets their
Annual leave days.
_Avoid_: antigüedad (alone), bienios

**Regime** (régimen):
Whether a person, from a given date, is under the Estatuto APS (with Annual leave and Administrative days) or on
honorarios (without them).
_Avoid_: contrato, tipo de contrato, calidad jurídica

**Rule version** (versión de la norma):
One dated version of a legal rule, with its legal basis and the date it takes effect; it reaches establishments
through app updates, and each counted Request keeps the version it was counted with.
_Avoid_: configuración, parámetro, política

**Recount** (recálculo):
Counting registered Requests again after a rule, a holiday or a person's data changed; it shows every difference
before it writes the correcting Movements.
_Avoid_: reproceso, recalcular todo

**Movement** (movimiento):
One dated line that changes a Balance (a yearly grant, a use, a cancellation or a correction), with who made it and
why.
_Avoid_: transacción, ajuste, edición

**Record sheet** (planilla de respaldo):
The Excel copy of every Balance and Request that the app rewrites after each change, for review, printing and as the
fallback when the app is down.
_Avoid_: planilla (alone: in the suite it means the people spreadsheet), base de datos, export

**Request** (solicitud):
A person's ask for days of one Leave type, which counts only after it is approved and then Registered.
_Avoid_: petición, formulario, ticket

**Registered** (registrada):
The state of a Request the HR officer has entered in the official record; only then does it change a Balance.
_Avoid_: closed, finalizada, procesada

**Balance** (saldo):
The days a person still has of one Leave type in its period, as the official record stands.
_Avoid_: cupo, contador

**Available** (disponible):
The Balance minus the days of Requests still waiting or approved but not yet Registered; no Request may go above it.
_Avoid_: saldo libre, restante

**Closed year** (año cerrado):
A year after its Year close; nothing more is written in it, and any later correction of it is one Movement in the
current year.
_Avoid_: año bloqueado, histórico

**Withdraw** (desistir):
What a person does to a Request nobody has decided yet; it leaves no Movement.
_Avoid_: retirar (in the suite it means disabling an account), cancelar

**Cancellation** (anulación):
The undoing of an approved Request, confirmed by the Jefatura and Registered, which gives the days back.
_Avoid_: rechazo, reversa

**Accumulation** (acumulación):
A person's express Request to carry unused Annual leave into the next year, never beyond two periods.
_Avoid_: arrastre, traspaso

**Year close** (cierre de año):
The 1 January Movements the app writes on its own: the year's grants, and the unused days the law lets lapse.
_Avoid_: reset, reinicio

**Annual leave** (feriado legal):
The paid yearly leave of Ley 19.378 art. 18, counted in días hábiles.
_Avoid_: vacaciones, feriado (alone)

**Administrative day** (día administrativo):
One of the paid days for personal reasons of Ley 19.378 art. 17, taken whole or as a half day.
_Avoid_: permiso administrativo (in the UI), día libre

**Día hábil** / **día corrido**:
Kept in Spanish, as Jefatura is. A día hábil is a day that is not a Saturday, Sunday or Public holiday; días corridos
count every day of a range. Each Leave type counts in one of them (API values `habiles` and `corridos`; in code
`Unit::Habiles` and `Unit::Corridos`).
_Avoid_: días laborales, business days

**Public holiday** (día feriado):
A national, regional or local holiday, which is never a día hábil; the national ones come from an outside list, the
regional and local ones are added by the HR officer.
_Avoid_: feriado (alone: it also names Annual leave)

**Sick leave** (licencia médica):
A period a doctor's licence keeps a person away, recorded only by its dates, never by its diagnosis.
_Avoid_: licencia (alone), reposo

**Receipt** (comprobante):
The printable PDF of a Registered Request, with its trail and lines for hand signatures, for the paper personnel
file.
_Avoid_: certificado, resolución

**Absences calendar** («Ausencias del personal»):
The read-only calendar, open only to the Jefaturas and the Dirección, that shows each Registered absence as a name
and «Ausente», never its reason.
_Avoid_: calendario de vacaciones, agenda

### Ratings and career

**Rating** (calificación):
A person's yearly performance evaluation under Decreto 1889, which places them in one List.
_Avoid_: evaluación de desempeño, nota

**List** (lista):
The result of a Rating: Lista 1 Distinción, 2 Buena, 3 Condicional or 4 Eliminación.

**Career score** (puntaje de carrera funcionaria):
The points from experience and training that set a person's level within their category.
_Avoid_: puntaje (alone), score
