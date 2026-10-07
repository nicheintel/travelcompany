# Rebuilds site/assets/data/us-cities.txt from the "zipcodes" npm package (BSD license):
#   npm pack zipcodes && tar -xzf zipcodes-*.tgz
#   python3 -I tools/build_us_cities.py package/lib/codes.js site/assets/data/us-cities.txt
# Build the US city list ("City, ST") from the zipcodes dataset (USPS city names for every ZIP code).
import json, sys, re, collections
src, out = sys.argv[1], sys.argv[2]
raw = open(src, encoding='utf-8').read()
codes, _ = json.JSONDecoder().raw_decode(raw, raw.index('{'))  # the first object (exports.codes)
STATES = set('AL AK AZ AR CA CO CT DE DC FL GA HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT NE NV NH NJ NM NY NC ND OH OK OR PA RI SC SD TN TX UT VT VA WA WV WI WY'.split())
seen = {}
zips = collections.Counter()  # ZIP codes per city: a good stand-in for size, so bigger cities are listed first
skipped = collections.Counter()
for z, d in codes.items():
    st = (d.get('state') or '').strip().upper(); city = re.sub(r'\s+', ' ', (d.get('city') or '').strip())
    if st not in STATES: skipped[st] += 1; continue
    if not city: continue
    # USPS mail-processing names that aren't towns (business reply mail, bulk mail centers, …)
    if re.search(r'\b(bmc|brm|ndc|amf|gmf|pdc|irs|usps|nsc)\b|parcel return|facility$', city, re.I): skipped['postal facility'] += 1; continue
    if city.isupper() or city.islower(): city = city.title()
    key = (city.lower(), st)
    seen.setdefault(key, f'{city}, {st}'); zips[key] += 1
items = [seen[k] for k in sorted(seen, key=lambda k: (-zips[k], seen[k].lower()))]
open(out, 'w', encoding='utf-8').write('\n'.join(items) + '\n')
print('ZIP codes:', len(codes), '| cities:', len(items), '| skipped (territories/military):', dict(skipped.most_common(8)))
print('first:', items[:12])
for chk in ['Atlanta, GA', 'Novi, MI', 'Morgantown, WV', 'Columbia, MD', 'The Villages, FL', 'New York, NY', 'Honolulu, HI', 'Anchorage, AK', 'Washington, DC', 'Detroit, MI', 'Brooklyn, NY', 'Fort Wayne, IN']:
    print(f'  {chk}:', chk in seen.values())
