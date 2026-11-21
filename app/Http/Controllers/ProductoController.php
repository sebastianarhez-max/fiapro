<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Catálogo de productos/servicios de la tienda del usuario logueado. */
class ProductoController extends Controller
{
    public const MODALIDADES = ['UNICO' => 'Pago único', 'CUOTAS' => 'En cuotas', 'AMBOS' => 'Ambos'];

    public function index()
    {
        $productos = DB::table('producto')
            ->where('tienda_id', Auth::user()->tienda_id)
            ->orderByDesc('estado')
            ->orderBy('descripcion')
            ->get();

        return view('productos.index', ['productos' => $productos, 'modalidades' => self::MODALIDADES]);
    }

    public function formulario()
    {
        return view('productos.nuevo', ['modalidades' => self::MODALIDADES]);
    }

    public function guardar(Request $request)
    {
        $d = $request->validate([
            'proveedor' => 'nullable|string|max:120',
            'marca' => 'nullable|string|max:80',
            'descripcion' => 'required|string|max:200',
            'unidad_medida' => 'required|string|max:30',
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'precio_contado' => 'required|numeric|gt:0|max:9999999',
            'precio_lista' => 'required|numeric|gt:0|gte:precio_contado|max:9999999',
            'modalidad_venta' => 'required|in:' . implode(',', array_keys(self::MODALIDADES)),
        ], [
            'precio_lista.gte' => 'El precio de lista (crédito) no puede ser menor al precio al contado.',
            'imagen.max' => 'La imagen no debe pesar más de 2 MB.',
        ]);

        $imagenUrl = null;
        if ($request->hasFile('imagen')) {
            $nombre = Str::uuid() . '.' . $request->file('imagen')->extension();
            $request->file('imagen')->move(public_path('uploads/productos'), $nombre);
            $imagenUrl = '/uploads/productos/' . $nombre;
        }

        $id = (string) Str::uuid();

        DB::table('producto')->insert([
            'producto_id' => $id,
            'tienda_id' => Auth::user()->tienda_id,
            'proveedor' => $d['proveedor'] ?? null,
            'marca' => $d['marca'] ?? null,
            'descripcion' => $d['descripcion'],
            'unidad_medida' => $d['unidad_medida'],
            'imagen_url' => $imagenUrl,
            'precio_contado' => round((float) $d['precio_contado'], 2),
            'precio_lista' => round((float) $d['precio_lista'], 2),
            'modalidad_venta' => $d['modalidad_venta'],
            'estado' => 'activo',
        ]);

        Bitacora::registrar('alta_producto', 'producto', $id, [
            'descripcion' => $d['descripcion'], 'contado' => $d['precio_contado'], 'lista' => $d['precio_lista'],
        ]);

        return redirect('/productos')->with('success', 'Producto registrado correctamente.');
    }

    public function cambiarEstado(string $id)
    {
        $p = DB::table('producto')->where('producto_id', $id)->where('tienda_id', Auth::user()->tienda_id)->first();
        abort_if(! $p, 404);

        $nuevo = $p->estado === 'activo' ? 'inactivo' : 'activo';
        DB::table('producto')->where('producto_id', $id)->update(['estado' => $nuevo]);

        Bitacora::registrar($nuevo === 'activo' ? 'alta_producto' : 'baja_producto', 'producto', $id, ['descripcion' => $p->descripcion]);

        return back()->with('success', $nuevo === 'activo' ? 'Producto reactivado.' : 'Producto dado de baja.');
    }

    /** Cambia (o agrega) la imagen de un producto existente. */
    public function cambiarImagen(Request $request, string $id)
    {
        $p = DB::table('producto')->where('producto_id', $id)->where('tienda_id', Auth::user()->tienda_id)->first();
        abort_if(! $p, 404);
        $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'imagen.required' => 'Elige una imagen.',
            'imagen.max' => 'La imagen no debe pesar más de 2 MB.',
        ]);

        $nombre = Str::uuid() . '.' . $request->file('imagen')->extension();
        $request->file('imagen')->move(public_path('uploads/productos'), $nombre);
        $nueva = '/uploads/productos/' . $nombre;

        // Borra la imagen anterior si estaba en la carpeta de uploads
        if ($p->imagen_url && str_starts_with($p->imagen_url, '/uploads/productos/') && is_file(public_path($p->imagen_url))) {
            @unlink(public_path($p->imagen_url));
        }
        DB::table('producto')->where('producto_id', $id)->update(['imagen_url' => $nueva]);
        Bitacora::registrar('imagen_producto', 'producto', $id, ['descripcion' => $p->descripcion]);

        return back()->with('success', 'Imagen de «' . $p->descripcion . '» actualizada.');
    }
}
