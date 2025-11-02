<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalysisController;

Route::post('/analise-fazenda', [AnalysisController::class, 'analyze']);
