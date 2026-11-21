# FiaPro — Sistema de ventas al crédito ("fiado") para bodegas y tiendas

FiaPro es una aplicación web que ordena el **fiado** de las tiendas de barrio: la tienda registra a sus
clientes, les asigna una línea de crédito y vende sus productos al crédito; el sistema calcula el
cronograma de pagos **quincenal** con el **método francés**, controla la **mora** de cada cuota y
genera los **listados de pago** de cada quincena. El cliente entra con su propia cuenta y ve cuánto
debe, cuándo vence cada cuota y qué ha pagado.

Proyecto del curso **SI642 – Finanzas e Ingeniería Económica**, Universidad Peruana de Ciencias Aplicadas (UPC).

**Autor:** Cristhian Sebastián Arias Hernández

---

## ¿Qué hace?

| Rol | Lo que puede hacer |
|---|---|
| **Administrador del sistema** | Registra tiendas, las activa o suspende y revisa la bitácora de acciones. |
| **Administrador de tienda** | Registra clientes y su configuración de crédito, gestiona el catálogo de productos, registra compras al crédito, cobra cuotas, genera listados de pago y usa el simulador de cronograma. |
| **Cliente** | Consulta su línea de crédito disponible, sus compras, el cronograma de cuotas, la mora a la fecha, sus pagos y su listado quincenal. |

### Módulos

- **Clientes y crédito:** límite de crédito, plazo máximo en meses, moneda (PEN/USD), tasa efectiva
  anual (TEA) compensatoria y TEA moratoria.
- **Productos:** catálogo con imagen, precio y modalidad de pago permitida (contado/pago único o en cuotas).
- **Compras al crédito:** valida el límite de crédito disponible, el plazo máximo del cliente y la modalidad
  del producto; genera y guarda el plan de pagos.
- **Cobros:** calcula días de atraso e interés moratorio por cuota, e imputa el pago en el orden
  mora → interés → capital.
- **Listados quincenales:** se generan solos todos los días a las 06:00 (`fiapro:generar-listados`) y también a mano.
- **Simulador de cronograma:** calcula el cronograma sin registrar la compra.
- **Bitácora:** registro de las acciones importantes del sistema.

## Modelo financiero

Todo el crédito es **quincenal**: no hay período 0 ni fecha de corte separada; la primera cuota vence
una quincena después de la compra.

- **Tasa efectiva quincenal (TEQ)** a partir de la TEA, con año comercial de 360 días:

  `TEQ = (1 + TEA)^(15/360) − 1`

- **Período de gracia opcional** de 0 a 3 quincenas, dentro del plazo. Durante la gracia los intereses
  se capitalizan (gracia total):

  `S = P · (1 + TEQ)^g`

- **Número de cuotas:** `n = meses × 2 − g`

- **Cuota fija (método francés):**

  `C = S · TEQ / (1 − (1 + TEQ)^(−n))`

- **Pago único:** también se puede comprar para pagar en una sola quincena, con el interés de 15 días.

- **Mora por cuota** (días comerciales 30/360) sobre el monto vencido de esa cuota:

  `Mora = Monto vencido · ((1 + TEA_mor)^(días de atraso/360) − 1)`

## Tecnologías

- PHP 8.4 · Laravel 13 · Blade
- PostgreSQL (producción) o MySQL / SQLite (local)
- Docker + Render (despliegue)
- PHPUnit (pruebas de los cálculos financieros con los juegos de datos del informe)

## Ejecutar en local

```bash
composer install
cp .env.example .env
php artisan key:generate
# configura DB_CONNECTION y los datos de tu base en .env
php artisan migrate
php artisan db:seed --class=DemoDataSeeder   # opcional: datos de demostración
php artisan serve
```

Abrir `http://127.0.0.1:8000`.

Pruebas:

```bash
php artisan test
```

## Despliegue en la web (Render)

El repositorio incluye `Dockerfile` y `render.yaml`:

1. En Render: **New → Blueprint** y elegir este repositorio.
2. Render crea el servicio web (Docker) y la base de datos PostgreSQL, genera `APP_KEY` y conecta
   `DB_URL` automáticamente.
3. Al arrancar, el contenedor ejecuta las migraciones y carga los datos de demostración si la base está vacía.

> En el plan gratuito el servicio se duerme tras unos minutos sin uso (la primera carga tarda ~1 minuto)
> y la base de datos gratuita dura 30 días.

## Estructura principal

```
app/Http/Controllers/      Controladores (compras, pagos, listados, clientes, productos, admin)
app/Services/              Cálculos financieros (cronograma francés, cuenta corriente y mora)
app/Console/Commands/      Tarea programada de listados quincenales
database/migrations/       Tablas del negocio (tienda, cliente, producto, compra, obligación, pago, listado)
database/seeders/          Datos de demostración
resources/views/           Pantallas (Blade)
tests/                     Pruebas automáticas
docker/, Dockerfile        Imagen para el despliegue
render.yaml                Blueprint de Render
```
