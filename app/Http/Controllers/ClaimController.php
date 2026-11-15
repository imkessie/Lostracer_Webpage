<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    public function store(Request $request, Item $item)
    {
        // 1. Validate the form inputs
        $request->validate([
            'proof_description' => 'required|string',
            'proof_file'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // 2. Upload proof photo/file to storage
        $filePath = null;
        if ($request->hasFile('proof_file')) {
            $filePath = $request->file('proof_file')->store('claims_proof', 'public');
        }

        // 3. Save to claims table
        Claim::create([
            'item_id'          => $item->id,
            'user_id'          => auth()->id(),
            'proof_descrition' => $request->proof_description, // Matches database column
            'proof_file_path'  => $filePath,
            'claim_token'      => strtoupper(Str::random(10)),  // Generates unique 10-char token
            'status'           => 'pending',
        ]);

        return redirect()->back()->with('success', 'Your claim request has been submitted! Security will review your proof.');
    }
}