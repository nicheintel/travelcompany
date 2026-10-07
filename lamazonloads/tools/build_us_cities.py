# Rebuilds site/assets/data/us-cities.txt (the sign-up city picker) from a ZIP code list:
# a tab-separated file with a header row, then one "ZIP<TAB>City, ST" line per ZIP code (USPS names).
#   python3 -I tools/build_us_cities.py zip_state.txt site/assets/data/us-cities.txt
# Output: one "City, ST<TAB>ZIP ZIP …" line per city, bigger cities (more ZIP codes) first.
# includes/apply.php (us_city_valid) and assets/app.js (the picker) read this format.
import sys, re, collections
src, out = sys.argv[1], sys.argv[2]
STATES = set('AL AK AZ AR CA CO CT DE DC FL GA HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT NE NV NH NJ NM NY NC ND OH OK OR PA RI SC SD TN TX UT VT VA WA WV WI WY'.split())
FIX = {'D Lo': "D'Lo", 'Ty Ty': 'Ty Ty'}  # all-caps names in the source
names, zips, spell, skipped = {}, collections.defaultdict(list), collections.defaultdict(list), collections.Counter()
for n, line in enumerate(open(src, encoding='utf-8-sig')):
    parts = line.rstrip('\r\n').split('\t')
    if len(parts) != 2 or not re.fullmatch(r'\d{5}', parts[0].strip()):
        if n: skipped['unreadable line'] += 1
        continue
    z, place = parts[0].strip(), re.sub(r'\s+', ' ', parts[1]).strip()
    m = re.fullmatch(r"(.+), ([A-Z]{2})", place)
    if not m: skipped['unreadable line'] += 1; continue
    city, st = m.group(1), m.group(2)
    if st not in STATES: skipped['territories/military'] += 1; continue
    # USPS mail-processing names that aren't towns
    if re.search(r'\b(bmc|brm|ndc|amf|gmf|pdc|irs|usps|nsc|facility)\b|parcel return|&', city, re.I): skipped['postal facility'] += 1; continue
    if city.isupper(): city = FIX.get(city.title(), city.title())
    city = re.sub(r'-([a-z])', lambda m: '-' + m.group(1).upper(), city)  # "Winston-salem" -> "Winston-Salem"
    key = (re.sub(r"['.]", '', city.lower().replace('-', ' ')), st)  # one entry for "Winston Salem" and "Winston-Salem"
    names.setdefault(key, f'{city}, {st}'); zips[key].append(z); spell[key].append(f'{city}, {st}')
items = sorted(names, key=lambda k: (-len(zips[k]), names[k].lower()))
for k in items:  # the spelling used for most of a city's ZIP codes
    names[k] = collections.Counter(spell[k]).most_common(1)[0][0]
open(out, 'w', encoding='utf-8', newline='\n').write(''.join(f"{names[k]}\t{' '.join(sorted(zips[k]))}\n" for k in items))
print('cities:', len(items), '| ZIP codes:', sum(len(v) for v in zips.values()), '| skipped:', dict(skipped))
print('first:', [names[k] for k in items[:10]])
have = set(names.values())
for chk in ['Atlanta, GA', 'Novi, MI', 'Morgantown, WV', 'Columbia, MD', 'The Villages, FL', 'New York, NY', 'Honolulu, HI', 'Anchorage, AK', 'Washington, DC', 'Brooklyn, NY', "D'Lo, MS", 'Humble, TX', 'Saint Louis, MO']:
    print(f'  {chk}:', chk in have)
