<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PenjualanController extends Controller
{
    public function index()
    {
        return view('penjualan.index');
    }

    public function data(Request $request)
{
    $query = Penjualan::with(['member', 'user'])->orderBy('id_penjualan', 'desc');

    // Filter by date range
    if ($request->filled('from_date') && $request->filled('to_date')) {
        $query->whereBetween('created_at', [
            $request->from_date . ' 00:00:00',
            $request->to_date . ' 23:59:59'
        ]);
    }

    $penjualan = $query->get();

    return datatables()
        ->of($penjualan)
        ->addIndexColumn()
        ->addColumn('total_item', fn($p) => format_uang($p->total_item))
        ->addColumn('total_harga', fn($p) => 'KES ' . format_uang($p->total_harga))
        ->addColumn('bayar', fn($p) => 'KES ' . format_uang($p->bayar))
        ->addColumn('tanggal', fn($p) => tanggal_indonesia($p->created_at, false))
        ->addColumn('kode_member', fn($p) => '<span class="label label-success">'.($p->member->kode_member ?? '').'</span>')
        ->editColumn('diskon', fn($p) => $p->diskon . '%')
        ->editColumn('kasir', fn($p) => $p->user->name ?? '')
        ->addColumn('aksi', fn($p) => '
            <div class="btn-group">
                <button onclick="showDetail(`'. route('penjualan.show', $p->id_penjualan) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-eye"></i></button>
                <button onclick="deleteData(`'. route('penjualan.destroy', $p->id_penjualan) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
            </div>
        ')
        ->rawColumns(['aksi', 'kode_member'])
        ->make(true);
}

    public function create()
    {
        $penjualan = Penjualan::create([
            'id_member' => null,
            'total_item' => 0,
            'total_harga' => 0,
            'diskon' => 0,
            'bayar' => 0,
            'diterima' => 0,
            'id_user' => auth()->id(),
        ]);

        session(['id_penjualan' => $penjualan->id_penjualan]);
        return redirect()->route('transaksi.index');
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $penjualan = Penjualan::findOrFail($request->id_penjualan);

            $penjualan->update([
                'id_member' => $request->id_member,
                'total_item' => $request->total_item,
                'total_harga' => $request->total,
                'diskon' => $request->diskon,
                'bayar' => $request->bayar,
                'diterima' => $request->diterima,
            ]);

            $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();

            foreach ($details as $item) {
                $item->update(['diskon' => $request->diskon]);

                $produk = Produk::find($item->id_produk);
                if (!$produk) continue;

                if ($produk->stok < $item->jumlah) {
                    DB::rollBack();
                    return redirect()->back()->with(
                        'error',
                        "❌ Insufficient stock for product: {$produk->nama_produk}. 
                        Available: {$produk->stok}, Requested: {$item->jumlah}"
                    );
                }

                $produk->decrement('stok', $item->jumlah);
            }

            DB::commit();
            return redirect()->route('transaksi.selesai')->with('success', 'Sale completed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error processing sale: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $detail = PenjualanDetail::with('produk')->where('id_penjualan', $id)->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', fn($d) => '<span class="label label-success">' . $d->produk->kode_produk . '</span>')
            ->addColumn('nama_produk', fn($d) => $d->produk->nama_produk)
            ->addColumn('harga_jual', fn($d) => 'KES ' . format_uang($d->harga_jual))
            ->addColumn('jumlah', fn($d) => format_uang($d->jumlah))
            ->addColumn('subtotal', fn($d) => 'KES ' . format_uang($d->subtotal))
            ->rawColumns(['kode_produk'])
            ->make(true);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $penjualan = Penjualan::findOrFail($id);
            $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();

            foreach ($details as $item) {
                if ($produk = Produk::find($item->id_produk)) {
                    $produk->increment('stok', $item->jumlah);
                }
                $item->delete();
            }

            $penjualan->delete();
            DB::commit();

            return response(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['error' => $e->getMessage()], 500);
        }
    }

    public function selesai()
    {
        $setting = Setting::first();
        return view('penjualan.selesai', compact('setting'));
    }

    public function notaKecil()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan'));

        if (!$penjualan) {
            abort(404);
        }

        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', session('id_penjualan'))
            ->get();

        return view('penjualan.nota_kecil', compact('setting', 'penjualan', 'detail'));
    }

    public function notaBesar()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan'));

        if (!$penjualan) {
            abort(404);
        }

        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', session('id_penjualan'))
            ->get();

        $pdf = Pdf::loadView('penjualan.nota_besar', compact('setting', 'penjualan', 'detail'))
                  ->setPaper('a5', 'portrait');

        return $pdf->stream('sales_report.pdf');
    }

    public function print(Request $request)
{
    $from_date = $request->input('from_date');
    $to_date = $request->input('to_date');

    if (!$from_date || !$to_date) {
        return back()->with('error', 'Please select a valid date range.');
    }

    // Use 'created_at' since 'tanggal' is not in your DB
    $penjualan = Penjualan::whereBetween('created_at', [$from_date, $to_date])->get();

    if ($penjualan->isEmpty()) {
        return back()->with('error', "No sales found between $from_date and $to_date.");
    }

    $pdf = Pdf::loadView('penjualan.print', [
        'penjualan' => $penjualan,
        'from_date' => $from_date,
        'to_date' => $to_date
    ])->setPaper('A4', 'portrait');

    return $pdf->stream('sales-report.pdf');
}

}
