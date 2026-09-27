@extends('layouts.app')

@section('title', ($student ? 'Edit' : 'Tambah').' siswa · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Siswa', 'route' => 'admin.students.index'],
            ['label' => $student ? 'Edit' : 'Tambah'],
        ];

        $hasClasses = $classes->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$student ? 'Edit data siswa' : 'Tambah siswa'"
        :description="$student
            ? 'Perubahan disimpan ke record siswa. Wali dan enrollment dikelola dari halaman detail siswa.'
            : 'Record siswa dapat langsung dilengkapi dengan wali pertama dan penempatan kelas pada langkah yang sama.'"
    />

    <x-form.error-summary />

    <form
        method="POST"
        action="{{ $student ? route('admin.students.update', $student) : route('admin.students.store') }}"
        class="space-y-6"
    >
        @csrf
        @if ($student) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="identity-heading">
                <h2 id="identity-heading" class="text-lg font-bold text-slate-950">Identitas</h2>

                <x-form.text name="legal_name" label="Nama lengkap" required :value="$student?->legal_name" maxlength="150" />
                <x-form.text name="preferred_name" label="Nama panggilan" :value="$student?->preferred_name" maxlength="150" />
                <x-form.text
                    name="student_number"
                    label="Nomor induk siswa"
                    :required="(bool) $student"
                    :value="$student?->student_number"
                    maxlength="50"
                    hint="{{ $student ? 'Nomor induk harus unik.' : 'Kosongkan untuk membuat nomor otomatis dari StudentNumberAllocator.' }}"
                />
                <x-form.text name="nisn" label="NISN" :value="$student?->nisn" maxlength="20" />
                <x-form.text name="national_id" label="Nomor identitas nasional" :value="$student?->national_id" maxlength="32" />

                <div>
                    <label for="gender" class="block text-sm font-medium text-slate-700">Jenis kelamin</label>
                    <select id="gender" name="gender" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30">
                        <option value="">Tidak diisi</option>
                        <option value="L" @selected(old('gender', $student?->gender) === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('gender', $student?->gender) === 'P')>Perempuan</option>
                    </select>
                    <x-form.field-error name="gender" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.date name="birth_date" label="Tanggal lahir" :value="$student?->birth_date" />
                    <x-form.text name="birth_place" label="Tempat lahir" :value="$student?->birth_place" maxlength="100" />
                </div>
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-lg font-bold text-slate-950">Status dan kontak</h2>

                <x-form.select
                    name="status"
                    label="Status siswa"
                    required
                    :value="$student?->status?->value ?? \App\Enums\StudentLifecycleStatus::Active->value"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                />
                <x-form.date name="admission_date" label="Tanggal masuk" required :value="$student?->admission_date ?? now()->toDateString()" />
                <x-form.date name="graduation_date" label="Tanggal lulus" :value="$student?->graduation_date" />
                <x-form.date name="leaving_date" label="Tanggal keluar" :value="$student?->leaving_date" />
                <x-form.textarea name="leaving_reason" label="Alasan keluar" :value="$student?->leaving_reason" :rows="2" maxlength="1000" />

                <x-form.text name="email" label="Email" type="email" :value="$student?->email" maxlength="150" />
                <x-form.text name="phone" label="Telepon" :value="$student?->phone" maxlength="30" />
                <x-form.textarea name="address" label="Alamat" :value="$student?->address" :rows="3" maxlength="2000" />
            </section>

            @unless ($student)
                <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="enrollment-heading">
                    <h2 id="enrollment-heading" class="text-lg font-bold text-slate-950">Enrollment awal (opsional)</h2>

                    @if ($hasClasses)
                        <label class="flex items-start gap-3 rounded-xl bg-cyan-50 p-4 text-sm text-cyan-900">
                            <input type="checkbox" name="has_enrollment" value="1" class="mt-1 rounded border-cyan-300 text-cyan-700 focus:ring-cyan-600" @checked(old('has_enrollment'))>
                            <span>Buat enrollment aktif pada kelas yang dipilih.</span>
                        </label>

                        <x-form.select
                            name="enrollment.class_id"
                            label="Kelas"
                            :options="$classes->mapWithKeys(fn ($class) => [$class->id => $class->getFullName().' · '.($class->academicYear?->code ?? '-').' ('.$class->status->label().')'])"
                            hint="Kelas ditampilkan dari tahun ajaran terbaru yang belum ditutup."
                        />
                        <x-form.date name="enrollment.enrolled_on" label="Tanggal enrollment" :value="now()->toDateString()" />
                        <x-form.date name="enrollment.effective_from" label="Tanggal mulai berlaku" :value="now()->toDateString()" />
                        <x-form.textarea name="enrollment.transition_reason" label="Catatan" :rows="2" maxlength="1000" />
                    @else
                        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm leading-6 text-slate-600">
                            Belum ada kelas yang dapat dipilih. Buat tahun ajaran dan kelas terlebih dahulu, atau tambahkan siswa tanpa enrollment lalu tempatkan dari
                            <a href="{{ route('admin.enrollments.create') }}" class="font-semibold text-cyan-700 hover:text-cyan-900">halaman enrollment</a>.
                        </p>
                    @endif
                </section>

                <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="guardian-heading">
                    <h2 id="guardian-heading" class="text-lg font-bold text-slate-950">Wali pertama (opsional)</h2>

                    <label class="flex items-start gap-3 rounded-xl bg-cyan-50 p-4 text-sm text-cyan-900">
                        <input type="checkbox" name="has_guardian" value="1" class="mt-1 rounded border-cyan-300 text-cyan-700 focus:ring-cyan-600" @checked(old('has_guardian'))>
                        <span>Tautkan satu wali ke siswa ini.</span>
                    </label>

                    <x-form.text name="guardian.legal_name" label="Nama wali" />
                    <x-form.select
                        name="guardian.relationship_type"
                        label="Hubungan"
                        :options="collect(\App\Enums\GuardianRelationshipType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])"
                    />
                    <x-form.text name="guardian.phone" label="Telepon wali" maxlength="30" />
                    <x-form.text name="guardian.email" label="Email wali" type="email" maxlength="150" />
                    <x-form.text name="guardian.national_id" label="Nomor identitas wali" maxlength="32" />

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="guardian[is_primary_contact]" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600" @checked(old('guardian.is_primary_contact'))>
                        Jadikan kontak utama
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="guardian[is_emergency_contact]" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600" @checked(old('guardian.is_emergency_contact'))>
                        Jadikan kontak darurat
                    </label>
                </section>
            @endunless
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ $student ? route('admin.students.show', $student) : route('admin.students.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
            <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                {{ $student ? 'Simpan perubahan' : 'Simpan siswa' }}
            </button>
        </div>
    </form>
@endsection
