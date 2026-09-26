<?php

namespace App\Enums;

/**
 * The document categories a PPDB applicant has to supply.
 *
 * The existing three cases are kept verbatim so historical rows and the public
 * one-shot PPDB form keep their meaning; the school-specific categories are
 * added rather than renamed. isRequired() is what the portal, the review screen
 * and the submission gate all read, so the required set is defined once.
 */
enum AdmissionDocumentType: string
{
    case Identity = 'identity';
    case BirthCertificate = 'birth_certificate';
    case PreviousSchoolReport = 'previous_school_report';
    case GraduationCertificate = 'graduation_certificate';
    case FamilyCard = 'family_card';
    case GuardianIdentity = 'guardian_identity';
    case PassPhoto = 'pass_photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'KTP / Identitas calon siswa',
            self::BirthCertificate => 'Akta kelahiran',
            self::PreviousSchoolReport => 'Rapor sekolah sebelumnya',
            self::GraduationCertificate => 'Surat keterangan lulus / ijazah terakhir',
            self::FamilyCard => 'Kartu keluarga',
            self::GuardianIdentity => 'KTP orang tua / wali',
            self::PassPhoto => 'Pas foto berwarna',
            self::Other => 'Dokumen pendukung',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Identity => 'Foto KTP calon siswa. PDF, JPG atau PNG.',
            self::BirthCertificate => 'Akta kelahiran memuat nama dan NIK yang sama dengan KTP.',
            self::PreviousSchoolReport => 'Rapor terakhir sekolah asal.',
            self::GraduationCertificate => 'SKL atau ijazah terakhir, sesuai keterangan pada rapor.',
            self::FamilyCard => 'Kartu keluarga terbaru.',
            self::GuardianIdentity => 'KTP orang tua atau wali yang menjadi kontak utama.',
            self::PassPhoto => 'Pas foto berwarna, latar merah atau biru.',
            self::Other => 'Dokumen lain yang diminta administrator.',
        };
    }

    /**
     * The categories that must exist before an application can be submitted.
     *
     * `previous_school_report` is deliberately excluded: a candidate repeating a
     * grade has no previous report, and `other` is free-form.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [
            self::Identity,
            self::BirthCertificate,
            self::GraduationCertificate,
            self::FamilyCard,
            self::GuardianIdentity,
            self::PassPhoto,
        ];
    }

    public function isRequired(): bool
    {
        return in_array($this, self::required(), true);
    }

    /**
     * The categories the portal offers as an upload slot, in review order.
     *
     * @return list<self>
     */
    public static function portalCategories(): array
    {
        return [
            self::GraduationCertificate,
            self::BirthCertificate,
            self::FamilyCard,
            self::GuardianIdentity,
            self::PassPhoto,
            self::Identity,
            self::PreviousSchoolReport,
            self::Other,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Human readable list of the required categories, for a validation message.
     */
    public static function requiredLabelList(): string
    {
        return implode(', ', array_map(
            static fn (self $case): string => $case->label(),
            self::required(),
        ));
    }
}
