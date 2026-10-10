<?php
/* Traveler and contact details: the fields and checks shared by the booking form and the trip page's Edit forms. */
declare(strict_types=1);
defined('TC_APP') || exit;

const NAME_PATTERN = '/^\p{L}[\p{L}\p{M}\' .-]*$/u';

/** Special meals airlines can arrange (IATA codes), stored with the traveler. */
const MEAL_PREFERENCES = [
    'VLML' => 'Vegetarian (with dairy and eggs)',
    'VGML' => 'Vegan',
    'MOML' => 'Muslim meal (halal)',
    'HNML' => 'Hindu meal',
    'KSML' => 'Kosher meal',
    'DBML' => 'Diabetic meal',
    'GFML' => 'Gluten-free meal',
    'CHML' => 'Child meal',
];
// i18n-keys: 'Vegetarian (with dairy and eggs)', 'Vegan', 'Muslim meal (halal)', 'Hindu meal', 'Kosher meal', 'Diabetic meal', 'Gluten-free meal', 'Child meal'

/**
 * Checks the traveler fields (t{i}_first, t{i}_last, t{i}_dob, …) against the quote's traveler slots.
 * $bagOffer: travelers may ask for a checked bag (t{i}_bag). Returns [travelers, errors].
 */
function validate_travelers(array $quote, array $values, bool $airTravel, bool $bagOffer = false): array
{
    $travelers = [];
    $errors = [];
    foreach ($quote['slots'] as $i => $slot) {
        $first = trim((string) ($values["t{$i}_first"] ?? ''));
        $noLast = $airTravel && !empty($values["t{$i}_nolast"]);
        $last = $noLast ? '' : trim((string) ($values["t{$i}_last"] ?? ''));
        $dob = trim((string) ($values["t{$i}_dob"] ?? ''));
        if ($first === '' || mb_strlen($first) > 50 || !preg_match(NAME_PATTERN, $first)) $errors["t{$i}_first"] = t('Enter a first name as it appears on the passport.');
        if (!$noLast && ($last === '' || mb_strlen($last) > 50 || !preg_match(NAME_PATTERN, $last))) $errors["t{$i}_last"] = t('Enter a last name as it appears on the passport.');
        $extra = [];
        if ($airTravel) {
            // What airlines need to issue a ticket (and check-in needs to match).
            $gender = (string) ($values["t{$i}_gender"] ?? '');
            $nat = strtoupper((string) ($values["t{$i}_nationality"] ?? ''));
            if (!in_array($gender, ['M', 'F'], true)) $errors["t{$i}_gender"] = t('Choose the gender shown on the passport or ID.');
            if (!isset(COUNTRY_DIAL[$nat])) $errors["t{$i}_nationality"] = t('Choose a nationality.');
            $extra = ['gender' => $gender, 'nationality' => $nat] + ($noLast ? ['no_last_name' => true] : []);
            $ff = strtoupper(trim((string) ($values["t{$i}_ff"] ?? '')));
            if ($ff !== '') {
                if (!preg_match('/^[A-Z0-9][A-Z0-9 -]{3,29}$/', $ff)) $errors["t{$i}_ff"] = t('Enter the frequent flyer number (letters and numbers only), or leave it empty.');
                $extra['frequent_flyer'] = $ff;
            }
            $meal = strtoupper(trim((string) ($values["t{$i}_meal"] ?? '')));
            if ($meal !== '') {
                if (!isset(MEAL_PREFERENCES[$meal])) $errors["t{$i}_meal"] = t('Choose a meal from the list, or leave it empty.');
                $extra['meal'] = $meal;
            }
            $redress = strtoupper(preg_replace('/[\s-]/', '', (string) ($values["t{$i}_redress"] ?? '')));
            if ($redress !== '') {
                if (!preg_match('/^[A-Z0-9]{5,15}$/', $redress)) $errors["t{$i}_redress"] = t('Enter the redress number (letters and numbers only), or leave it empty.');
                $extra['redress'] = $redress;
            }
            if ($bagOffer && !str_starts_with($slot['label'], 'Infant') && !empty($values["t{$i}_bag"])) $extra['extra_bag'] = true;
        }
        if ($slot['dob']) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
            $start = new DateTimeImmutable($quote['start_date']);
            // Age on the travel date; -1 for invalid or future dates.
            $age = $d && $d->format('Y-m-d') === $dob && $d <= $start ? $d->diff($start)->y : -1;
            if ($age < 0 || $age > 120) $errors["t{$i}_dob"] = t('Enter a valid date of birth.');
            elseif (str_starts_with($slot['label'], 'Child') && ($age < 2 || $age > 11)) $errors["t{$i}_dob"] = t('Children must be 2–11 years old on the travel date.');
            elseif (str_starts_with($slot['label'], 'Adult') && $age < 12) $errors["t{$i}_dob"] = t('Adults must be 12 or older on the travel date.');
            elseif (str_starts_with($slot['label'], 'Infant') && $d->diff(new DateTimeImmutable($quote['end_date'] ?? $quote['start_date']))->y >= 2) $errors["t{$i}_dob"] = t('Infants must be under 2 for the whole trip — book them as a child instead.');
        }
        $travelers[] = ['first' => $first, 'last' => $last] + ($slot['dob'] ? ['dob' => $dob] : []) + $extra;
    }
    return [$travelers, $errors];
}

/** Checks the contact fields (contact_name, email, phone_country, phone). Returns [[name, email, phone], errors]. */
function validate_contact(array $values): array
{
    $errors = [];
    $name = preg_replace('/\s+/', ' ', trim((string) ($values['contact_name'] ?? '')));
    $email = normalize_email((string) ($values['email'] ?? ''));
    $country = strtoupper((string) ($values['phone_country'] ?? ''));
    $number = preg_replace('/[\s().-]/', '', (string) ($values['phone'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !preg_match(NAME_PATTERN, $name)) $errors['contact_name'] = t('Enter the name of the person we should contact.');
    if (!valid_email($email)) $errors['email'] = t('Enter a valid email address.');
    elseif (($fix = email_typo($email)) !== null) $errors['email'] = t('Check the spelling. Did you mean {email}?', ['email' => $fix]);
    if (!isset(COUNTRY_DIAL[$country])) $errors['phone'] = t('Choose the country code.');
    elseif (!preg_match('/^\+?[0-9]{4,15}$/', $number)) $errors['phone'] = t('Enter a valid mobile number.');
    // Stored as "+63 9171234567" (numbers typed with their own +code are kept as typed).
    $phone = str_starts_with($number, '+') ? $number : '+' . (COUNTRY_DIAL[$country] ?? '') . ' ' . ltrim($number, '0');
    return [[$name, $email, $phone], $errors];
}

/** A stored phone ("+63 9171234567") split back into the form's country and number. */
function split_phone(string $phone): array
{
    if (!preg_match('/^\+(\d{1,4}) (\d+)$/', $phone, $m)) return [default_phone_country(), $phone];
    $country = (string) (COUNTRY_DIAL[default_phone_country()] ?? '') === $m[1] ? default_phone_country() : (array_search($m[1], array_map('strval', COUNTRY_DIAL), true) ?: default_phone_country());
    return [$country, $m[2]];
}

/** The booking form's fields for one traveler; $v($key) gives a field's current value. */
function traveler_fieldset(int $i, array $slot, callable $v, array $errors, bool $airTravel, string $kind): string
{
    ob_start(); ?>
    <fieldset class="space-y-4 border-t border-slate-100 pt-5 first:border-0 first:pt-0">
      <legend class="mb-3 text-sm font-semibold text-brand-700"><?= e(slot_label($slot['label'])) ?></legend>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= text_field("t{$i}_first", $airTravel ? t('First name (as on passport)') : t('First name'), $v("t{$i}_first"), 'text', $errors["t{$i}_first"] ?? null, ['autocomplete' => $i === 0 ? 'given-name' : 'off']) ?>
        <div class="space-y-1.5">
          <?= text_field("t{$i}_last", $airTravel ? t('Last name (surname)') : t('Last name'), $v("t{$i}_last"), 'text', $errors["t{$i}_last"] ?? null, ['autocomplete' => $i === 0 ? 'family-name' : 'off']) ?>
          <?php if ($airTravel): ?><label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="t<?= $i ?>_nolast" value="1"<?= $v("t{$i}_nolast") !== '' ? ' checked' : '' ?> class="h-4 w-4 accent-brand-600"> <?= e(t('No surname on passport')) ?></label><?php endif; ?>
        </div>
      </div>
      <?php if ($airTravel): ?>
        <div class="grid gap-4 sm:grid-cols-3">
          <?= select_field("t{$i}_gender", t('Gender on passport/ID'), ['M' => t('Male'), 'F' => t('Female')], $v("t{$i}_gender"), $errors["t{$i}_gender"] ?? null, t('Choose…')) ?>
          <?= text_field("t{$i}_dob", t('Date of birth'), $v("t{$i}_dob"), 'date', $errors["t{$i}_dob"] ?? null, ['min' => '1900-01-01', 'max' => today()]) ?>
          <?= select_field("t{$i}_nationality", t('Nationality'), country_list(), $v("t{$i}_nationality"), $errors["t{$i}_nationality"] ?? null, t('Choose…')) ?>
        </div>
        <?php if ($kind === 'flight'): $open = $v("t{$i}_ff") . $v("t{$i}_meal") . $v("t{$i}_redress") !== '' || isset($errors["t{$i}_ff"]) || isset($errors["t{$i}_meal"]) || isset($errors["t{$i}_redress"]); ?>
          <details class="group"<?= $open ? ' open' : '' ?>>
            <summary class="cursor-pointer text-sm font-semibold text-brand-700 hover:underline"><?= e(t('Frequent flyer, meal and redress number (optional)')) ?></summary>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
              <?= text_field("t{$i}_ff", t('Frequent flyer (airline and number)'), $v("t{$i}_ff"), 'text', $errors["t{$i}_ff"] ?? null, ['autocomplete' => 'off', 'placeholder' => 'PR 1234567']) ?>
              <?= select_field("t{$i}_meal", t('Meal preference'), array_map('t', MEAL_PREFERENCES), $v("t{$i}_meal"), $errors["t{$i}_meal"] ?? null, t('No preference')) ?>
              <?= text_field("t{$i}_redress", t('Redress number'), $v("t{$i}_redress"), 'text', $errors["t{$i}_redress"] ?? null, ['autocomplete' => 'off'], t('Only if the US government gave you one.')) ?>
            </div>
          </details>
        <?php endif; ?>
      <?php elseif ($slot['dob']): ?><div class="sm:w-1/2 sm:pr-2"><?= text_field("t{$i}_dob", t('Date of birth'), $v("t{$i}_dob"), 'date', $errors["t{$i}_dob"] ?? null, ['min' => '1900-01-01', 'max' => today()]) ?></div><?php endif; ?>
    </fieldset>
    <?php return (string) ob_get_clean();
}

/** The contact fields (name, email, mobile with country code); $v($key) gives a field's current value. */
function contact_fields(callable $v, array $errors): string
{
    ob_start(); ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <?= text_field('contact_name', t('Contact name'), $v('contact_name'), 'text', $errors['contact_name'] ?? null, ['autocomplete' => 'name']) ?>
      <?= text_field('email', t('Email'), $v('email'), 'email', $errors['email'] ?? null, ['autocomplete' => 'email']) ?>
      <div class="sm:col-span-2">
        <label for="f_phone" class="mb-1.5 block text-sm font-medium text-slate-700"><?= e(t('Mobile phone')) ?></label>
        <div class="flex gap-2">
          <select name="phone_country" aria-label="<?= e(t('Country code')) ?>" class="w-32 shrink-0 rounded-xl border border-slate-300 bg-white px-3 py-3 text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 sm:w-44">
            <?php $pc = $v('phone_country') ?: default_phone_country(); foreach (country_list() as $cc => $cname): ?><option value="<?= e($cc) ?>"<?= $cc === $pc ? ' selected' : '' ?>><?= e("+" . COUNTRY_DIAL[$cc] . " · $cname") ?></option><?php endforeach; ?>
          </select>
          <input id="f_phone" name="phone" type="tel" value="<?= e($v('phone')) ?>" autocomplete="tel-national" placeholder="917 123 4567"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="f_phone_err"' : '' ?> class="min-w-0 flex-1 rounded-xl border bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-2 <?= isset($errors['phone']) ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100' ?>">
        </div>
        <?php if (isset($errors['phone'])): ?><p id="f_phone_err" class="mt-1.5 text-sm text-red-600"><?= e($errors['phone']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}
