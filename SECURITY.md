# Seguridad

## Credencial local conocida en el repositorio público

El 2026-10-19 se publicó, en la nota ODD de la interfaz de métodos de pago, la
contraseña del MariaDB local de XAMPP que se usó para una base de test aislada.
La línea se redactó el 2026-10-23, pero el valor sigue recuperable en la
historia (`b914f45`, `2fd17a2`) y en cuatro cabeceras de PR cerradas.

Decisión registrada el 2026-10-23: **se acepta el riesgo y no se rota la
contraseña** mientras se cumplan todas las condiciones de abajo. El motivo es
que la base es local, no tiene datos clínicos reales y el servidor no escucha en
red.

Condiciones que la aceptación asume:

- MariaDB de XAMPP con `skip-networking` y sin listener en 3306/tcp.
- phpMyAdmin bajo `Require local`; sin túneles, port-forwarding, WSL en modo
  _mirrored_ ni reglas de firewall que expongan 80/443.
- La contraseña no se reutiliza en ningún otro servicio, equipo ni despliegue.
- La base no guarda datos clínicos reales (PHI).

Disparadores que obligan a rotar: exponer el equipo o la base, reutilizar la
clave en otro sitio, desplegar con este `.env`, compartir un dump, o mover datos
reales a la base.

## Gate de secretos

El job `security` de `.github/workflows/ci.yml` corre `gitleaks` sobre los
commits que publica cada push o PR, con el juego por defecto más las reglas de
`.gitleaks.toml` (una contraseña de base escrita como valor y la forma
`root/<clave>` de las notas ODD). No re-escanea la historia: el hallazgo
conocido de arriba ya está publicado y teñiría todas las corridas. La regla es
que **ninguna credencial nueva se publica**.

## Reportar un secreto

No abra una issue pública con el valor. Repórtelo en privado al mantenedor del
repositorio.
