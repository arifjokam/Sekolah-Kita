{{--
    Guardian fields, shared by the "add" and "edit" forms.

    The same field set is used for both so a candidate never has to learn a
    second set of labels halfway through, and so validation error names resolve
    identically in both directions.
--}}
@php $isEdit = $guardian !== null; @endphp

<x-form.text name="legal_name" label="Nama lengkap wali" required maxlength="150" :value="$guardian?->legal_name" />
<x-form.text name="national_id" label="NIK wali" required inputmode="numeric" maxlength="16" :value="$guardian?->national_id" />
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.select
        name="relationship_type"
        label="Hubungan"
        required
        :value="$guardian?->relationship_type?->value"
        :options="collect($relationships)->mapWithKeys(fn ($relationship) => [$relationship->value => $relationship->label()])"
    />
    <x-form.text name="occupation" label="Pekerjaan" maxlength="100" :value="$guardian?->occupation" />
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.text name="phone" type="tel" label="Nomor telepon" required maxlength="30" :value="$guardian?->phone" />
    <x-form.text name="whatsapp_number" type="tel" label="Nomor WhatsApp" maxlength="30" :value="$guardian?->whatsapp_number" hint="Kosongkan untuk memakai nomor telepon." />
</div>
<x-form.text name="email" type="email" label="Email" maxlength="150" :value="$guardian?->email" />
<x-form.textarea name="address" label="Alamat wali" rows="2" maxlength="500" :value="$guardian?->address" />

<div class="space-y-2">
    <label class="flex gap-3 text-sm text-slate-700">
        <input type="checkbox" name="is_primary_contact" value="1" @checked($guardian?->is_primary_contact)
               class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
        <span>Jadikan kontak utama</span>
    </label>
    <label class="flex gap-3 text-sm text-slate-700">
        <input type="checkbox" name="is_emergency_contact" value="1" @checked($guardian?->is_emergency_contact)
               class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
        <span>Jadikan kontak darurat</span>
    </label>
</div>

@if ($withSubmit ?? false)
    <button type="submit" class="w-full rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
        {{ $isEdit ? 'Simpan perubahan wali' : 'Tambah wali' }}
    </button>
@endif
