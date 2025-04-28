<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function simulate(Request $request)
    {
        $validated = $request->validate([
            'rent' => 'required|numeric|min:0',
            'deposit' => 'required|numeric|min:0',
            'key_money' => 'required|numeric|min:0',
            'management_fee' => 'nullable|numeric|min:0',
            'savings' => 'required|numeric|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'food_expense' => 'nullable|numeric|min:0',
            'utility_expense' => 'nullable|numeric|min:0',
            'communication_expense' => 'nullable|numeric|min:0',
        ]);

        $rent = $validated['rent'];
        $management_fee = $validated['management_fee'] ?? 0;

        $initial_cost = ($rent * ($validated['deposit'] + $validated['key_money'] + 1 + 1)) + $management_fee;

        $monthly_expenses = $rent + $management_fee + 
            ($validated['food_expense'] ?? 40000) + 
            ($validated['utility_expense'] ?? 15000) + 
            ($validated['communication_expense'] ?? 10000);

        $remaining_savings = $validated['savings'] - $initial_cost;
        $monthly_balance = $validated['monthly_income'] - $monthly_expenses;

        return view('home', array_merge($validated, [
            'initial_cost' => $initial_cost,
            'monthly_expenses' => $monthly_expenses,
            'remaining_savings' => $remaining_savings,
            'monthly_balance' => $monthly_balance,
        ]));
    }
}