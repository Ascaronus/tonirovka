import http.client, concurrent.futures, json, subprocess
HOST='127.0.0.1:8080'
def request(method, path='/contact-click.php', body=None, origin=None):
    c=http.client.HTTPConnection(HOST)
    headers={'Content-Type':'application/x-www-form-urlencoded'}
    if origin: headers['Origin']=origin
    c.request(method,path,body,headers);r=c.getresponse();data=r.read();c.close();return r.status,data
assert request('GET')[0]==405
assert request('POST',body='service=telegram&place=footer')[0]==403
assert request('POST',body='service=telegram&place=footer',origin='https://evil.example')[0]==403
origin='http://'+HOST
assert request('POST',body='service=unknown&place=footer',origin=origin)[0]==400
assert request('POST',body='service[]=telegram&place=footer',origin=origin)[0]==400
assert request('POST',body='x'*600,origin=origin)[0]==413
with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
    results=list(pool.map(lambda _:request('POST',body='service=telegram&place=footer',origin=origin)[0],range(30)))
assert results==[204]*30,results
raw=subprocess.check_output(['docker','exec','seo-test','cat','/var/www/html/data/contact-clicks.json'])
data=json.loads(raw)
assert sum(d.get('telegram',{}).get('footer',0) for d in data['days'].values())==30,data
assert request('GET','/data/contact-clicks.json')[0]==403
assert request('GET','/admin/contact_stats_store.php')[0]==403
assert request('GET','/admin/nav.php')[0]==403
assert request('GET','/admin/contact-stats.php')[0]==302
assert request('GET','/admin/guide.php')[0]==302
print('Contact HTTP: concurrent counts, rejected requests, private data and authenticated pages passed')
