<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class testJsonToDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:jsondb';

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
        $file = json_decode(Storage::disk('public')->get('data.json'), true);

        foreach ($file as $key => $value) {
            echo "$key \n";
            // $value2 = $value;
            // $value2['levelPenaksir'] = 1;
            // $value2['isFinal'] = 1;
            DB::connection('mysql')->table('tran_taksiran')->insert($value);
            // DB::connection('mysql')->table('tran_taksiran')->insert($value2);
        }
        // ini_set('memory_limit', -1);
        // DB::connection('mysql')->table('tran_taksiran')->insert($file);

        return 0;
    }
}
