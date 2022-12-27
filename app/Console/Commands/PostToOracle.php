<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PostToOracle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:oracle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // dd(date('m/d/Y-H:i:s'));

        /* AMBIL DATA DARI TRANSAKSI SUMMARY */
        $sqlBatch = "SELECT batch FROM acc_sm_trans WHERE isPost = 0 LIMIT 10";
        $dataBatch = DB::connection('mysql')->select(DB::raw($sqlBatch));
        $stringDataBatch = "";

        /* DIJADIKAN SEBAGAI PARAMETER IN */
        foreach ($dataBatch as $key => $value) {
            $stringDataBatch .= "$value->batch,";
        }
        $stringDataBatch = substr($stringDataBatch, 0, -1);

        $sqlDetail = "SELECT
            kodeTransaksi AS TrxNumber,
            namaJenisJurnal AS TrxType,
            DATE_FORMAT(dt.tanggal, '%d-%b-%y') AS TrxDate,
            sm.batch,
            CASE
                WHEN LEFT(coaCabang, 1) = 1 THEN '999'
                WHEN LEFT(coaCabang, 1) = 2 THEN '002'
                WHEN LEFT(coaCabang, 1) = 3 THEN '003'
                WHEN LEFT(coaCabang, 1) = 4 THEN '004'
                WHEN LEFT(coaCabang, 1) = 5 THEN '005'
                WHEN LEFT(coaCabang, 1) = 6 THEN '006'
                WHEN LEFT(coaCabang, 1) = 7 THEN '007'
                WHEN LEFT(coaCabang, 1) = 8 THEN '008'
            END AS Company,
            LEFT(coaCabang, 3) AS Outlet,
            1 AS TipePembayaran,
            '000' AS CostCenter,
            ct.coaOracle AS NaturalAccount,
            CASE
                WHEN LEFT(kodeTransaksi, 1) = 0 THEN (SELECT productOracleValue FROM trans_gadai tg LEFT JOIN tblproduk tp ON tp.idProduk = tg.idProduk WHERE noSbg = kodeTransaksi LIMIT 1)
                ELSE ''
            END AS Product,
            CASE
                WHEN idUsedfor != 0 THEN '0000'
                ELSE '0000'
            END AS Intercompany,
            '000' AS Future1,
            '000' AS Future2,
            CASE
                WHEN dk = 'D' THEN dt.amount ELSE 0
            END AS Debit,
            CASE
                WHEN dk = 'K' THEN dt.amount ELSE 0
            END AS Credit,
            dt.keterangan AS Reference,
            'IDR' AS CurrencyCode,
            ct.accountType AS AccountType,
            1 AS ExchangeRate,
            '' AS BankReference
        FROM `acc_dt_trans` dt
        LEFT JOIN acc_sm_trans sm ON sm.batch = dt.batch
        LEFT JOIN acc_jenisjurnal jj ON jj.idJenisJurnal = sm.idJenisJurnal
        LEFT JOIN tblcoatemplate ct ON ct.idCoa = dt.idCoa
        -- LEFT JOIN tblcoausedfor cuf ON cuf.idUsedfor = ct.idUsedfor
        WHERE dt.batch IN ($stringDataBatch);";

        $dataBatchDetail = DB::connection('mysql')->select(DB::raw($sqlDetail));

        $dataPost = array(
            'BatchName' => date('m/d/Y-H:i:s'),
            'CountLine' => count($dataBatchDetail),
            'Journal' => $dataBatchDetail
        );

        return 0;
    }
}
