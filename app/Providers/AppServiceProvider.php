<?php

namespace App\Providers;

use App\Models\Court;
use App\Models\Dependence;
use App\Models\Doctor;
use Illuminate\Support\ServiceProvider;
use App\Models\Log; // porque quieres excluirlo
use App\Models\Penalty;
use App\Models\Publicsecurities;
use App\Models\Traffic;
use App\Models\User;
use App\Observers\GenericObserver;
use App\Observers\PenaltyObserver;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{


    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        User::observe(GenericObserver::class);
    

        
    }
}
