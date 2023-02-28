<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PostToOracleAllWilayah extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:oracle_all {--limit=5000} {--tanggal=""}';

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
        $limit = $this->option('limit');
        $tanggal = $this->option('tanggal') == ''? date('Y-m-d') : $this->option('tanggal');

        $sqlWilayah = "SELECT
            kodeCabang,
            LEFT(CONVERT(kodeCabang, UNSIGNED), 1) AS filterWilayah
        FROM tblcabang
        WHERE idJenisCabang=2 ORDER BY kodeCabang";

        $dataWilayah = DB::connection('mysql')->select(DB::raw($sqlWilayah));

        if(count($dataWilayah))
        {
            foreach ($dataWilayah as $key => $value) {
                Artisan::call("post:oracle_wilayah --limit=$limit --wilayah=$value->filterWilayah --tanggal=$tanggal");
                sleep(5);
            }
        }

        return 0;
    }
}
