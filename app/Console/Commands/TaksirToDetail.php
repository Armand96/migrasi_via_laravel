<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TaksirToDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:taksir_detail_copy';

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
        // $sql = "SELECT
        //             taksir.idFapg, idTaksiran, noKtp
        //         FROM tran_taksiran taksir
        //         LEFT JOIN tran_fapg fapg ON taksir.idFAPG = fapg.idFAPG
        //         WHERE isFinal = 1
        //     ";
        ini_set('memory_limit', '-1');
        $sql = "SELECT
                    idTaksiran,
                    idBarangEmas,
                    ems.idJenisEmas,
                    namaBarangEmas,
                    jumlah,
                    keterangan_barang AS keterangan,
                    tks.karat,
                    berat_kotor AS beratKotor,
                    berat_bersih AS beratBersih,
                    namaJenisEmas,
                CASE
                        WHEN ems.idJenisEmas = 1 THEN
                        harga_stle_antam
                        WHEN ems.idJenisEmas = 2 THEN
                        harga_stle_non_antam
                        WHEN ems.idJenisEmas = 3 THEN
                        harga_stle_perhiasan
                    END AS hargaStle,
                    nilai_taksir AS taksiran,
                    ltv,
                    total_nilai_pinjaman AS nilaiPinjaman,
                    ltv AS ltvKaUnit,
                    total_nilai_pinjaman AS nilaipinjamanKaUnit,
                    1 AS isActive
                FROM tran_taksiran taksir
                LEFT JOIN tran_fapg fapg ON taksir.idFAPG = fapg.idFAPG
                LEFT JOIN dummy_getDataTaksir tks ON tks.no_id = fapg.noKtp
                LEFT JOIN tblbarangemas ems ON ems.kodeBarangEmas = tks.fk_barang
                LEFT JOIN tbljenisemas jms ON jms.idJenisEmas = ems.idJenisEmas
                WHERE taksir.isFinal = 1
            ";

        $dataTaksiran = DB::connection('mysql')->select(DB::raw($sql));

        $tempDataInsertTaksiranDetail = [];

        if(count($dataTaksiran))
        {
            foreach ($dataTaksiran as $index => $dataT) {
                // $sqlDetail = "SELECT
                //         $dataT->idTaksiran AS idTaksiran,
                //         idBarangEmas,
                //         ems.idJenisEmas,
                //         namaBarangEmas,
                //         jumlah,
                //         keterangan_barang AS keterangan,
                //         karat,
                //         berat_kotor AS beratKotor,
                //         berat_bersih AS beratBersih,
                //         namaJenisEmas,
                //         CASE WHEN ems.idJenisEmas = 1 THEN harga_stle_antam WHEN ems.idJenisEmas = 2 THEN harga_stle_non_antam WHEN ems.idJenisEmas = 3 THEN harga_stle_perhiasan END AS hargaStle,
                //         nilai_taksir AS taksiran,
                //         ltv,
                //         total_nilai_pinjaman AS nilaiPinjaman,
                //         ltv as ltvKaUnit,
                //         total_nilai_pinjaman AS nilaipinjamanKaUnit,
                //         1 AS isActive
                //     FROM dummy_getDataTaksir tks
                //     LEFT JOIN tblbarangemas ems ON ems.kodeBarangEmas = tks.fk_barang
                //     LEFT JOIN tbljenisemas jms ON jms.idJenisEmas = ems.idJenisEmas
                //     WHERE no_id = '$dataT->noKtp'
                // ";

                // $dataDetail = DB::connection('mysql')->select(DB::raw($sqlDetail));

                // foreach ($dataDetail as $key => $value) {

                //     array_push($tempDataInsertTaksiranDetail, (array) $value);
                //     echo "$key \n";
                // }

                // array_push($tempDataInsertTaksiranDetail, (array) $dataT);
                echo "$index $dataT->idTaksiran -- $dataT->namaBarangEmas \n";
                DB::connection('mysql')->table('tran_taksirandetail')->insert((array) $dataT);
            }
            // dd($tempDataInsertTaksiranDetail);
            // DB::connection('mysql')->table('tran_taksirandetail')->insert($tempDataInsertTaksiranDetail);
        }


        return 0;
    }
}
