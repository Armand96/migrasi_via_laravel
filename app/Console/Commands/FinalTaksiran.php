<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalTaksiran extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:final_taksiran';

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
        $sql = "SELECT
            idFAPG,
            jumlahBarang,
            namaJaminan,
            karat,
            beratKotor,
            beratBersih,
            avgKarat,
            sumBeratKotor,
            sumBeratBersih,
            totalTaksiran,
            keterangan,
            levelPenaksir,
            isFinal
        FROM tran_taksiran";
        $dataTaksiran = DB::connection('mysql')->select(DB::raw($sql));

        foreach ($dataTaksiran as $key => $value) {
            echo "$key \n";
            $value->levelPenaksir = 1;
            $value->isFinal = 1;
            $value = (array) $value;
            DB::connection('mysql')->table('tran_taksiran')->insert($value);
        }

        return 0;
    }
}
