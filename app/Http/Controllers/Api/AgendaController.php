<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agenda;
use App\Models\Banom;
use Illuminate\Support\Str;

class AgendaController extends Controller
{
    private function resolveOrganizerLogo(?string $organizer, ?string $manualLogo): ?string
    {
        if (!empty($manualLogo)) {
            return $manualLogo;
        }

        $normalized = Str::lower(trim((string) $organizer));
        if ($normalized === '') {
            return null;
        }

        $slug = Str::lower(Str::slug($organizer));
        $banom = Banom::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->orWhereRaw('LOWER(slug) = ?', [$slug])
            ->first();

        return $banom?->logo;
    }

    private function enrichAgendaPayload(array $validated, ?Agenda $existing = null): array
    {
        if (array_key_exists('ticket_quota', $validated) && $validated['ticket_quota'] === null) {
            $validated['ticket_quota'] = 0;
        }

        if (
            (!array_key_exists('ticket_quota_label', $validated) || empty($validated['ticket_quota_label'])) &&
            array_key_exists('ticket_quota', $validated) &&
            $validated['ticket_quota'] !== null
        ) {
            $validated['ticket_quota_label'] = (string) $validated['ticket_quota'];
        }

        if (!array_key_exists('ticket_info_title', $validated) || empty($validated['ticket_info_title'])) {
            $validated['ticket_info_title'] = $existing?->ticket_info_title ?: 'Informasi Tiket';
        }

        if (!array_key_exists('registration_button_text', $validated) || empty($validated['registration_button_text'])) {
            $validated['registration_button_text'] = $existing?->registration_button_text ?: 'Daftar Sekarang';
        }

        $validated['organizer_verified'] = true;

        $organizerName = array_key_exists('organizer', $validated) ? $validated['organizer'] : $existing?->organizer;
        $manualLogo = array_key_exists('organizer_logo', $validated) ? $validated['organizer_logo'] : $existing?->organizer_logo;
        $validated['organizer_logo'] = $this->resolveOrganizerLogo($organizerName, $manualLogo);

        return $validated;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Agenda::orderBy('date_start', 'asc')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date_start' => 'required|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'time_string' => 'nullable|string',
            'location' => 'required|string',
            'maps_url' => 'nullable|url',
            'image' => 'nullable|string',
            'status' => 'required|in:upcoming,ongoing,completed',
            'rundown' => 'nullable|array',
            'gallery' => 'nullable|array',
            'ticket_price' => 'nullable|string',
            'ticket_quota' => 'nullable|integer',
            'ticket_quota_label' => 'nullable|string|max:255',
            'ticket_info_title' => 'nullable|string|max:120',
            'organizer' => 'nullable|string',
            'organizer_logo' => 'nullable|string',
            'organizer_verified' => 'boolean',
            'registration_enabled' => 'boolean',
            'registration_url' => 'nullable|url|required_if:registration_enabled,1',
            'registration_button_text' => 'nullable|string|max:100',
            'registration_note' => 'nullable|string|max:255',
            'registration_closed_text' => 'nullable|string|max:255',
            'registration_open_until' => 'nullable|date',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . time();
        $validated = $this->enrichAgendaPayload($validated);

        $agenda = Agenda::create($validated);

        return response()->json($agenda, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Try to find by ID first, then by slug
        if (is_numeric($id)) {
            $agenda = Agenda::find($id);
        } else {
            $agenda = Agenda::where('slug', $id)->first();
        }

        if (!$agenda) {
            return response()->json(['message' => 'Agenda not found'], 404);
        }

        return $agenda;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $agenda = Agenda::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'date_start' => 'sometimes|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'time_string' => 'nullable|string',
            'location' => 'sometimes|string',
            'maps_url' => 'nullable|url',
            'image' => 'nullable|string',
            'status' => 'sometimes|in:upcoming,ongoing,completed',
            'rundown' => 'nullable|array',
            'gallery' => 'nullable|array',
            'ticket_price' => 'nullable|string',
            'ticket_quota' => 'nullable|integer',
            'ticket_quota_label' => 'nullable|string|max:255',
            'ticket_info_title' => 'nullable|string|max:120',
            'organizer' => 'nullable|string',
            'organizer_logo' => 'nullable|string',
            'organizer_verified' => 'boolean',
            'registration_enabled' => 'boolean',
            'registration_url' => 'nullable|url|required_if:registration_enabled,1',
            'registration_button_text' => 'nullable|string|max:100',
            'registration_note' => 'nullable|string|max:255',
            'registration_closed_text' => 'nullable|string|max:255',
            'registration_open_until' => 'nullable|date',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . $agenda->id;
        }

        $validated = $this->enrichAgendaPayload($validated, $agenda);

        $agenda->update($validated);

        return response()->json($agenda);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $agenda = Agenda::findOrFail($id);
        $agenda->delete();
        return response()->json(['message' => 'Agenda deleted successfully']);
    }

    /**
     * Get unique organizer names for the export filter.
     */
    public function organizers()
    {
        $organizers = Agenda::select('organizer')
            ->whereNotNull('organizer')
            ->where('organizer', '!=', '')
            ->distinct()
            ->orderBy('organizer')
            ->pluck('organizer');

        return response()->json($organizers);
    }

    /**
     * Get agenda data filtered by organizer for export.
     */
    public function exportData(Request $request)
    {
        $request->validate([
            'organizer' => 'required|string',
        ]);

        $agendas = Agenda::where('organizer', $request->organizer)
            ->orderBy('date_start', 'asc')
            ->get();

        return response()->json($agendas);
    }
}

