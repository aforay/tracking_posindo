<?php

namespace App\Http\Controllers;

use App\Models\PostOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostOfficeController extends Controller
{
    /**
     * List all post office contacts
     */
    public function index(Request $request)
    {
        $query = PostOffice::query();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('province', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('phone_wa', 'LIKE', "%{$search}%")
                  ->orWhere('pic_name', 'LIKE', "%{$search}%");
            });
        }

        $offices = $query->orderBy('province', 'asc')->orderBy('city', 'asc')->orderBy('name', 'asc')->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $offices,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $offices,
        ]);
    }

    /**
     * Store a new post office contact
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'phone_wa' => 'required|string|max:30',
            'pic_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $postOffice = PostOffice::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kontak Kantor Pos berhasil ditambahkan.',
            'data' => $postOffice,
        ]);
    }

    /**
     * Update an existing post office contact
     */
    public function update(Request $request, $id)
    {
        $postOffice = PostOffice::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'phone_wa' => 'required|string|max:30',
            'pic_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $postOffice->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kontak Kantor Pos berhasil diperbarui.',
            'data' => $postOffice,
        ]);
    }

    /**
     * Delete a post office contact
     */
    public function destroy($id)
    {
        $postOffice = PostOffice::findOrFail($id);
        $postOffice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kontak Kantor Pos berhasil dihapus.',
        ]);
    }

    /**
     * Bulk import post office contacts
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'offices' => 'required|array',
            'offices.*.name' => 'required|string',
            'offices.*.phone_wa' => 'required|string',
        ]);

        $imported = 0;
        foreach ($request->offices as $item) {
            PostOffice::updateOrCreate(
                ['name' => $item['name']],
                [
                    'code' => $item['code'] ?? null,
                    'city' => $item['city'] ?? null,
                    'province' => $item['province'] ?? null,
                    'phone_wa' => $item['phone_wa'],
                    'pic_name' => $item['pic_name'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]
            );
            $imported++;
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$imported} data kontak Kantor Pos.",
        ]);
    }
}
