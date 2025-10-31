@extends('layouts.master')

@section('title')
    Sales List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales List</li>
@endsection

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border d-flex justify-content-between align-items-center">
                <h4 class="box-title">Sales List</h4>

                <div class="d-flex align-items-center">
                    <input type="date" id="from_date" class="form-control mr-2" style="width: 180px;">
                    <input type="date" id="to_date" class="form-control mr-2" style="width: 180px;">
                    <button class="btn btn-primary mr-2" id="filterBtn">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <button class="btn btn-secondary mr-2" id="resetBtn">
                        <i class="fa fa-refresh"></i> Reset
                    </button>
                    <button class="btn btn-success" id="printSalesBtn">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered table-penjualan table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Date</th>
                        <th>Member Code</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Discount</th>
                        <th>Total Pay</th>
                        <th>Cashier</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('penjualan.detail')
@endsection

@push('scripts')
<script>
let table, table1;

$(function () {
    // ✅ Initialize main table
    table = $('.table-penjualan').DataTable({
        responsive: true,
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: '{{ route('penjualan.data') }}',
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
            }
        },
        columns: [
            {data: 'DT_RowIndex', searchable: false, sortable: false},
            {data: 'tanggal'},
            {data: 'kode_member'},
            {data: 'total_item'},
            {data: 'total_harga'},
            {data: 'diskon'},
            {data: 'bayar'},
            {data: 'kasir'},
            {data: 'aksi', searchable: false, sortable: false},
        ]
    });

    // ✅ Filter button
    $('#filterBtn').click(function () {
        table.ajax.reload();
    });

    // ✅ Reset button
    $('#resetBtn').click(function () {
        $('#from_date').val('');
        $('#to_date').val('');
        table.ajax.reload();
    });

    // ✅ Detail table
    table1 = $('.table-detail').DataTable({
        processing: true,
        bSort: false,
        dom: 'Brt',
        columns: [
            {data: 'DT_RowIndex', searchable: false, sortable: false},
            {data: 'kode_produk'},
            {data: 'nama_produk'},
            {data: 'harga_jual'},
            {data: 'jumlah'},
            {data: 'subtotal'},
        ]
    });

    // ✅ Print filtered report
    $('#printSalesBtn').click(function () {
        let from_date = $('#from_date').val();
        let to_date = $('#to_date').val();

        if (!from_date || !to_date) {
            alert('Please select both From and To dates before printing.');
            return;
        }

        const printUrl = `/penjualan/print?from_date=${from_date}&to_date=${to_date}`;
        window.open(printUrl, '_blank');
    });
});

// ✅ Show sale detail
function showDetail(url) {
    $('#modal-detail').modal('show');
    table1.ajax.url(url);
    table1.ajax.reload();
}

// ✅ Delete sale
function deleteData(url) {
    if (confirm('Are you sure you want to delete this sale?')) {
        $.post(url, {
            '_token': $('[name=csrf-token]').attr('content'),
            '_method': 'delete'
        })
        .done(() => table.ajax.reload())
        .fail(() => alert('Unable to delete data.'));
    }
}
</script>
@endpush
