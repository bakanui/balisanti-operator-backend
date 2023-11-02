<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Master\KapalController;
use App\Http\Controllers\Master\JenisPenumpangController;
use App\Http\Controllers\Master\DermagaController;
use App\Http\Controllers\Master\RuteController;
use App\Http\Controllers\Master\SOPController;
use App\Http\Controllers\Master\AgenController;
use App\Http\Controllers\Master\NahkodaController;
use App\Http\Controllers\Master\HargaServiceController;
use App\Http\Controllers\Master\JadwalTiketController;
use App\Http\Controllers\Master\JenisKapalController;
use App\Http\Controllers\Master\KecakapanNahkodaController;
use App\Http\Controllers\Master\GaleryController;
use App\Http\Controllers\Flow\PenjualanTiketController;
use App\Http\Controllers\Flow\PembayaranTiketController;
use App\Http\Controllers\Flow\PembayaranAgenController;
use App\Http\Controllers\Flow\PenumpangController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Flow\ManifestController;
use App\Http\Controllers\Laporan\LapOperatorController;
use App\Http\Controllers\Laporan\LapAgenController;
use App\Http\Controllers\Laporan\LapPenumpangController;
use App\Http\Controllers\Dashboard\DashboardController;

use App\Http\Middleware\CheckIfAdmin;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');
    Route::post('lupa-password', 'lupaPassword');
    Route::get('/login/google', 'redirectToGoogle');
    Route::get('/login/google/callback', 'handleGoogleCallback');
});

Route::prefix('master')->group(function() {
    Route::controller(KapalController::class)->prefix("kapal")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(JenisPenumpangController::class)->prefix("jenis-penumpang")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(DermagaController::class)->prefix("dermaga")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(RuteController::class)->prefix("rute")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view');
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(SOPController::class)->prefix("sop")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(AgenController::class)->prefix("agen")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(NahkodaController::class)->prefix("nahkoda")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });

    Route::controller(KecakapanNahkodaController::class)->prefix("kecakapan-nahkoda")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(HargaServiceController::class)->prefix("harga-service")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(JadwalTiketController::class)->prefix("jadwal-tiket")->group(function () {
        Route::get('', 'index');
        Route::post('balisanti', 'store');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
        Route::post('set-image', 'setImage')->middleware([CheckIfAdmin::class]);
    });
    
    Route::controller(JenisKapalController::class)->prefix("jenis-kapal")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });

    Route::controller(UserController::class)->prefix("users")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::put('', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });

    Route::controller(GaleryController::class)->prefix("galery")->group(function () {
        Route::get('', 'index');
        Route::get('view/{id}', 'view')->middleware([CheckIfAdmin::class]);
        Route::post('', 'store')->middleware([CheckIfAdmin::class]);
        Route::post('update', 'update')->middleware([CheckIfAdmin::class]);
        Route::delete('', 'delete')->middleware([CheckIfAdmin::class]);
    });

    Route::controller(PenumpangController::class)->prefix("penumpang")->group(function () {
        Route::get('', 'findByPhone')->middleware([CheckIfAdmin::class]);
    });

});

Route::controller(PenjualanTiketController::class)->prefix('penjualan')->group(function () {
    Route::get('cari-tiket', 'cariTiket');
    Route::get('cari-jadwal', 'cariJadwal');
    Route::get('cari-jadwal-non-token', 'cariJadwalTanpaToken');
    Route::get('cari-jenispenumpang-by-jadwal', 'cariJenisPenumpangJadwal');
    Route::post('', 'create');
    Route::get('log/tiket-ordered', 'logTiketOrdered');
    // Route::get('email-create-order', 'emailCreateOrder');
});

Route::controller(DermagaController::class)->group(function () {
    Route::get('dermaga', 'dermagaTanpaToken');   
});

Route::controller(UserController::class)->group(function () {
    Route::get('user/reset-password', 'resetPassword');   
});

Route::controller(PembayaranTiketController::class)->prefix('pembayaran')->group(function () {
    Route::get('search-by-invoice', 'searchByInvoice');
    Route::post('', 'pelunasan');
    Route::get('recap', 'getRecap');
    Route::get('download-invoice-pdf', 'downloadInvoicePDF');
    Route::get('download-multiple-invoice-pdf', 'downloadMultiInvoicePDF');
    Route::get('no_invoice-suggestion', 'noInvoiceSuggestion');
});

Route::controller(PembayaranAgenController::class)->prefix('pembayaran-agen')->group(function () {
    Route::get('', 'index');
    Route::get('list-invoice/{id_agen}', 'getListInvoice');
    Route::get('list-invoice-dermaga/{id_dermaga}', 'getListInvoiceByDermaga');
    Route::get('list-penumpang-dermaga/{id_dermaga}', 'getListPenumpangByDermaga');
    Route::get('list-penumpang-jadwal/{id_jadwal}', 'getListPenumpangByJadwal');
    Route::get('list-penumpang-rutes', 'getListPenumpangByRutes');
});

Route::controller(ManifestController::class)->prefix('manifest')->group(function () {
    Route::get('', 'index');
    Route::post('bulk', 'manifestBulk');
});

Route::prefix('laporan')->group(function() {
    Route::controller(LapOperatorController::class)->prefix('operator')->group(function () {
        Route::get('', 'index');
        Route::get('recap', 'recap');
    });
    Route::controller(LapPenumpangController::class)->prefix('penumpang')->group(function () {
        Route::get('', 'index');
        Route::get('recap', 'recap');
    });
    Route::controller(LapAgenController::class)->prefix('agen')->group(function () {
        Route::get('', 'index');
        Route::get('recap', 'recap');
    });
});

Route::controller(PenumpangController::class)->prefix('tiket-penumpang')->group(function () {
    Route::put('edit-tiket', 'edit');
    Route::put('edit-invoice', 'editInvoice');
});

Route::controller(DashboardController::class)->prefix('dashboard')->group(function () {
    Route::get('', 'index');
});

// Route::get('test', fn () => phpinfo());