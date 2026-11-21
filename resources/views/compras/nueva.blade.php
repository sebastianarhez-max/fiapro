@extends('layouts.panel')
@section('titulo', 'Nueva compra | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <div class="crumbs"><a href="/compras">Compras</a> / Nueva</div>
            <h1>Registrar compra a crédito</h1>
            <p>El sistema valida el límite de crédito y genera el plan de pagos automáticamente.</p>
        </div>
    </div>

    @if($clientes->isEmpty() || $productos->isEmpty())
        <div class="alert warn">@include('partials.icono', ['n' => 'alert'])
            <div>Para registrar una compra necesitas al menos un cliente activo con configuración de crédito y un producto activo.
                <a href="/clientes/nuevo">Registrar cliente</a> · <a href="/productos/nuevo">Registrar producto</a></div>
        </div>
    @endif

    <form method="POST" action="/compras" id="form-compra">
        @csrf
        <div class="grid-3-1">
            <div class="card">
                <div class="form-grid">
                    <div class="full section-title"><span class="n">1</span> Cliente y producto</div>
                    <div class="full">
                        <label for="cliente_id">Cliente @include('partials.ayuda', ['t' => 'Solo aparecen clientes activos con condiciones de crédito registradas'])</label>
                        <select id="cliente_id" name="cliente_id" required>
                            <option value="">Selecciona un cliente…</option>
                            @foreach($clientes as $c)
                                <option value="{{ $c->cliente_id }}"
                                        data-moneda="{{ $c->moneda }}" data-limite="{{ $c->limite_credito }}" data-utilizado="{{ $c->utilizado }}"
                                        data-disponible="{{ $c->disponible }}" data-maxmeses="{{ $c->max_meses }}" data-tea="{{ $c->tasa_comp }}"
                                        @selected(old('cliente_id') === $c->cliente_id)>
                                    {{ $c->nombre_completo }} — {{ $c->documento_identidad }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label for="producto_id">Producto o servicio @include('partials.ayuda', ['t' => 'Se usa el precio de lista (crédito) del producto'])</label>
                        <select id="producto_id" name="producto_id" required>
                            <option value="">Selecciona un producto…</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->producto_id }}" data-precio="{{ $p->precio_lista }}" data-modalidad="{{ $p->modalidad_venta }}" data-img="{{ $p->imagen_url }}" data-nombre="{{ $p->descripcion }}"
                                        @selected(old('producto_id') === $p->producto_id)>
                                    {{ $p->descripcion }}{{ $p->marca ? ' · ' . $p->marca : '' }} — {{ number_format($p->precio_lista, 2) }} / {{ $p->unidad_medida }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="cantidad">Cantidad @include('partials.ayuda', ['t' => 'Unidades o medida del producto. Debe ser mayor a 0'])</label>
                        <input id="cantidad" name="cantidad" type="number" step="0.01" min="0.01" value="{{ old('cantidad', 1) }}" required>
                    </div>
                    <div>
                        <label for="fecha_compra">Fecha y hora de compra @include('partials.ayuda', ['t' => 'Desde esta fecha se cuentan las quincenas: el pago único o la primera cuota vence 15 días después'])</label>
                        <input id="fecha_compra" name="fecha_compra" type="datetime-local" value="{{ old('fecha_compra', now()->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="full section-title" style="margin-top:8px"><span class="n">2</span> Forma de pago</div>
                    <div>
                        <label for="modalidad_pago">Modalidad @include('partials.ayuda', ['t' => 'Pago único: se paga completo a los 15 días de la compra, con el interés de una quincena. En cuotas: método francés, una cuota cada 15 días contados desde la fecha de compra'])</label>
                        <select id="modalidad_pago" name="modalidad_pago" required>
                            <option value="UNICO" @selected(old('modalidad_pago', 'UNICO') === 'UNICO')>Pago único (a la quincena)</option>
                            <option value="CUOTAS" @selected(old('modalidad_pago') === 'CUOTAS')>En cuotas quincenales</option>
                        </select>
                    </div>
                    <div id="campo-meses">
                        <label for="numero_meses">Número de meses @include('partials.ayuda', ['t' => 'Cada mes equivale a 2 cuotas quincenales. No puede superar el máximo pactado con el cliente'])</label>
                        <input id="numero_meses" name="numero_meses" type="number" min="1" max="{{ config('fiapro.meses_max') }}" value="{{ old('numero_meses', config('fiapro.meses_defecto')) }}">
                        <div class="hint" id="hint-meses"></div>
                    </div>
                    <div id="campo-gracia">
                        <label for="periodos_gracia">Período de gracia @include('partials.ayuda', ['t' => 'Quincenas al inicio en que el cliente no paga (gracia total): el interés se suma al saldo. Van dentro del plazo: con 3 meses (6 quincenas) y 1 de gracia quedan 5 cuotas'])</label>
                        <select id="periodos_gracia" name="periodos_gracia">
                            <option value="0" @selected(old('periodos_gracia', '0') == '0')>Sin período de gracia</option>
                            @for($i = 1; $i <= \App\Services\CronogramaFrances::GRACIA_MAX; $i++)
                                <option value="{{ $i }}" @selected(old('periodos_gracia') == $i)>{{ $i }} {{ $i === 1 ? 'quincena' : 'quincenas' }} de gracia total</option>
                            @endfor
                        </select>
                        <div class="hint" id="hint-gracia"></div>
                    </div>
                </div>
            </div>

            <div class="card" style="position:sticky;top:20px">
                <h2>Resumen de la operación</h2>
                <div class="preview-foto" id="pv-foto" style="display:none">
                    <img class="foto foto-lg" id="pv-img" alt="">
                    <div><div class="cell-main" id="pv-nombre"></div><div class="cell-sub" id="pv-precio"></div></div>
                </div>
                <div class="preview">
                    <div class="row"><span class="muted">Monto de la compra</span><strong id="pv-monto">—</strong></div>
                    <div class="row"><span class="muted">Crédito utilizado</span><strong id="pv-usado">—</strong></div>
                    <div class="row"><span class="muted">Disponible antes</span><strong id="pv-disp">—</strong></div>
                    <div class="row"><span class="muted">Disponible después</span><strong id="pv-desp">—</strong></div>
                </div>
                <div class="meter" id="pv-meter"><span style="width:0%"></span></div>
                <div class="small muted" id="pv-cond">Selecciona un cliente para ver sus condiciones.</div>
                <div id="pv-msg" style="margin-top:12px"></div>
                <button class="btn" type="submit" style="width:100%;margin-top:16px" id="btn-guardar">@include('partials.icono', ['n' => 'save']) Registrar compra</button>
                <a class="btn ghost" href="/compras" style="width:100%;margin-top:8px">Cancelar</a>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const cli = $('cliente_id'), prod = $('producto_id'), cant = $('cantidad'), mod = $('modalidad_pago'), meses = $('numero_meses'), gracia = $('periodos_gracia');
    const fmt = (m, v) => (m === 'USD' ? 'US$ ' : 'S/ ') + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function actualizar() {
        const c = cli.selectedOptions[0], p = prod.selectedOptions[0];
        const enCuotas = mod.value === 'CUOTAS';
        $('campo-meses').style.display = enCuotas ? '' : 'none';
        $('campo-gracia').style.display = enCuotas ? '' : 'none';
        meses.required = enCuotas;

        // Gracia: debe quedar al menos una cuota (gracia < meses × 2)
        const nq = (parseInt(meses.value) || 0) * 2;
        [...gracia.options].forEach(o => o.disabled = parseInt(o.value) > 0 && parseInt(o.value) >= nq);
        if (gracia.selectedOptions[0] && gracia.selectedOptions[0].disabled) gracia.value = '0';
        const g = parseInt(gracia.value) || 0;
        $('hint-gracia').textContent = nq > 0 ? nq + ' quincenas = ' + (g > 0 ? g + ' de gracia + ' : '') + (nq - g) + ' cuotas.' : '';

        // Modalidad permitida por el producto
        if (p && p.dataset.modalidad) {
            const m = p.dataset.modalidad;
            [...mod.options].forEach(o => o.disabled = (m !== 'AMBOS' && o.value !== m));
            if (mod.selectedOptions[0].disabled) { mod.value = m; return actualizar(); }
        }

        if (p && p.value) {
            $('pv-foto').style.display = 'flex';
            $('pv-nombre').textContent = p.dataset.nombre;
            $('pv-precio').textContent = 'Precio a crédito: ' + Number(p.dataset.precio).toFixed(2);
            const img = $('pv-img');
            if (p.dataset.img) { img.src = p.dataset.img; img.style.display = ''; } else { img.removeAttribute('src'); img.style.display = 'none'; }
        } else { $('pv-foto').style.display = 'none'; }
        if (!c || !c.value) return;
        const moneda = c.dataset.moneda, disp = parseFloat(c.dataset.disponible), lim = parseFloat(c.dataset.limite), usado = parseFloat(c.dataset.utilizado);
        meses.max = c.dataset.maxmeses;
        $('hint-meses').textContent = 'Máximo pactado: ' + c.dataset.maxmeses + ' meses (' + (c.dataset.maxmeses * 2) + ' quincenas).';
        $('pv-cond').textContent = 'TEA ' + Number(c.dataset.tea).toFixed(2) + '%' + (enCuotas ? ' · cuotas cada 15 días desde la compra' : ' · se paga a los 15 días de la compra');
        $('pv-usado').textContent = fmt(moneda, usado);
        $('pv-disp').textContent = fmt(moneda, disp);

        const precio = p && p.value ? parseFloat(p.dataset.precio) : 0;
        const monto = Math.round(precio * (parseFloat(cant.value) || 0) * 100) / 100;
        $('pv-monto').textContent = p && p.value ? fmt(moneda, monto) : '—';
        const despues = disp - monto;
        $('pv-desp').textContent = fmt(moneda, Math.max(0, despues));
        const pct = lim > 0 ? Math.min(100, (usado + monto) / lim * 100) : 0;
        $('pv-meter').className = 'meter' + (pct > 85 ? ' alto' : '');
        $('pv-meter').firstElementChild.style.width = pct + '%';

        const msg = $('pv-msg'), btn = $('btn-guardar');
        if (p && p.value && despues < -0.000001) {
            msg.innerHTML = '<div class="alert err" style="margin:0">La compra supera el crédito disponible. Se rechazará.</div>';
            btn.disabled = true;
        } else if (p && p.value) {
            msg.innerHTML = '<div class="alert ok" style="margin:0">Dentro del límite de crédito.</div>';
            btn.disabled = false;
        } else { msg.innerHTML = ''; btn.disabled = false; }
    }
    [cli, prod, cant, mod, meses, gracia].forEach(e => e.addEventListener('input', actualizar));
    actualizar();
})();
</script>
@endpush
