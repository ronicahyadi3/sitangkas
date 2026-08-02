@extends('layouts.app')

@section('content')
    <div class="header bg-primary pb-6">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-6">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        @media (min-width: 576px) {
            .media_width{
                width: 50% !important
            }
        }
    </style>
    <div class="container-fluid mt--6 media_width">
        <div class="card">
            <br>
            <span style="color: rgb(255, 40, 40)"><i class="fas fa-low-vision fa-10x text-gray-300 d-flex justify-content-center"></i></span>
            <br>
            <div class="text-center px-3">
                <h2>ANDA TIDAK MEMILIKI AKSES</h2>
                <p>Mohon untuk Menghubungi Kami Bidang Statistik Dan Persandian.<br> Terima Kasih</p>
            </div>

            <hr style="margin-top: 0rem">
            <div class="text-center">
                <p>Admin Bidang Statistik dan Persandian <br>
                <span style="color: rgb(94, 114, 228)"><i class="fa fa-mobile" aria-hidden="true"></i></span> 0812-7788-6070</p>
            </div>
        </div>
    </div>

@endsection
