/**
 * Sekolah Kita — test data for the load harness.
 *
 * IMPORTANT: this file must never contain real applicant data, real NIK
 * numbers, real email addresses, or real credentials. Populate SLUGS from a
 * staging seed, not from production.
 *
 * Slugs are supplied through the environment so the harness does not need to
 * be edited when content changes:
 *
 *   k6 run -e SLUGS='berita-1,berita-2,berita-3' tests/load/public-read.js
 */

export const PAGES = [
  '/',
  '/berita',
  '/prestasi',
  '/galeri',
  '/guru',
  '/profil-sekolah',
  '/ppdb',
  '/kontak',
  '/login',
];

export const DETAIL_SLUGS = (__ENV.SLUGS || '')
  .split(',')
  .map((s) => s.trim())
  .filter((s) => s !== '');

export const PPDB_SLUGS = (__ENV.PPDB_SLUGS || '')
  .split(',')
  .map((s) => s.trim())
  .filter((s) => s !== '');

/**
 * Synthetic identity used for the write scenarios.
 *
 * The PPDB limiter allows 5 submissions per hour per IP. A load test that
 * honours the limit cannot generate write load; one that ignores it tests a
 * configuration the application never runs. Writes therefore run against a
 * staging environment with limits deliberately raised, and the fact that they
 * were raised is recorded with the result.
 *
 * Never point these at production.
 */
export const SYNTHETIC = {
  email: __ENV.TEST_EMAIL || 'loadtest+candidate@staging.sekolah.example',
  password: __ENV.TEST_PASSWORD || 'load-test-only-not-a-real-secret',
  applicantName: 'Beban Uji Synthetic',
  applicantNik: '320000000000',
  guardianName: 'Wali Beban Uji Synthetic',
  guardianPhone: '080000000000',
};

export const ADMIN = {
  email: __ENV.TEST_ADMIN_EMAIL || '',
  password: __ENV.TEST_ADMIN_PASSWORD || '',
};
