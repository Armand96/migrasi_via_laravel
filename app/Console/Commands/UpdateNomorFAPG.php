<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateNomorFAPG extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:nomor_fapg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Nomor FAPG';

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
        $sql = "SELECT
                    tahun,
                    kodeCabang
                FROM tran_fapg
                WHERE nomor IS NULL
                GROUP BY tahun, kodeCabang";

        $getGroupTahunKDCabang = DB::connection('mysql')->select(DB::raw($sql));

        // Storage::disk('public')->put('grouptahuncabang.json', json_encode($getGroupTahunKDCabang));

        foreach ($getGroupTahunKDCabang as $index => $data) {
            $sqlSub = "SELECT MAX(nomor) AS nomor FROM tran_fapg WHERE tahun = '$data->tahun' AND kodeCabang = '$data->kodeCabang' LIMIT 1";
            $maxNumber = DB::connection('mysql')->select(DB::raw($sqlSub));

            // Storage::disk('public')->put('max.json', json_encode($maxNumber));

            $valMaxNumber = intval($maxNumber[0]->nomor)+1;
            $sqlForLoop = "SELECT idFAPG, nomor, nomorFAPG, kodeCabang, tahun FROM tran_fapg WHERE tahun = '$data->tahun' AND kodeCabang = '$data->kodeCabang' AND nomor IS NULL ORDER BY tanggalTaksir, kodeCabang ASC";
            $dataForLoop = DB::connection('mysql')->select(DB::raw($sqlForLoop));

            // Storage::disk('public')->put('images.json', json_encode($dataForLoop));

            foreach ($dataForLoop as $indexChunk => $dataUpdate) {
                $twoDigitYear = substr($dataUpdate->tahun, -2);
                $nomor = str_pad($valMaxNumber, 5,  "0", STR_PAD_LEFT);
                $nomorFAPG = $dataUpdate->kodeCabang . "." . $twoDigitYear . ".". $nomor ;
                // dd($nomorFAPG, $dataForLoop);
                $sqlUpdate = "UPDATE tran_fapg SET nomor = '$nomor', nomorFAPG = '$nomorFAPG' WHERE idFAPG = $dataUpdate->idFAPG;";
                DB::connection('mysql')->statement($sqlUpdate);
                $valMaxNumber += 1;
                echo "$data->tahun -- $data->kodeCabang -- $nomorFAPG \n";
            }

            // $chunkLoop = array_chunk($dataForLoop, 5000);
            // foreach ($chunkLoop as $chunk) {

            // }
        }
        return 0;
    }
}
