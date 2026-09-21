const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert=require('assert');
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});const page=await browser.newPage();const origin='http://127.0.0.1:8080';
 await page.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());
 async function open(file){const r=await page.goto(origin+'/admin/'+file);assert.equal(r.status(),200,file);const text=await page.locator('body').innerText();assert(!/Fatal error|Warning:|Parse error|Не удалось завершить операцию/.test(text),file+': '+text.slice(0,500));return text;}
 async function post(file,extra,formIndex=0){await open(file);const fields=await page.locator('form').nth(formIndex).evaluate(f=>Object.fromEntries(new FormData(f)));const r=await page.request.post(origin+'/admin/'+file,{form:{...fields,...extra}});assert(r.status()<400,file+': '+r.status()+' '+(await r.text()).slice(0,500));return r;}
 try{
  await open('index.php');await page.locator('[name=username]').fill('audit');await page.locator('[name=password]').fill('Audit-Only-12345');await Promise.all([page.waitForNavigation(),page.locator('button').click()]);
  for(const file of ['index.php','content.php','prices.php','films.php','gallery.php','guide.php','settings.php','logs.php','export_logs.php','seo-auto.php','setup-seo-monitoring.php','contact-stats.php','check_gd.php','changelog.php']){await open(file);console.log('Admin GET: '+file);}
  // Each mutation starts from a fresh form revision.
  await post('prices.php',{action:'add',name_uk:'Тест $1 «ціна»',name_ru:'Тест $1 цена',price:'900-1000 ₴'});
  let homepage=await(await page.request.get(origin+'/')).text();assert(homepage.includes('Тест $1 «ціна»'),'Literal price title preserved');
  await open('prices.php');const stale=await page.locator('form').first().evaluate(f=>Object.fromEntries(new FormData(f)));
  await post('prices.php',{action:'edit',index:'3',name_uk:'Тестова ціна',name_ru:'Тестовая цена',price:'1000-1200 ₴'});
  const conflict=await page.request.post(origin+'/admin/prices.php',{form:{...stale,action:'delete',index:'0'}});assert.equal(conflict.status(),409,'Stale form rejected');
  await post('prices.php',{action:'delete',index:'3'});
  await open('content.php');const contentForm=page.locator('form').filter({has:page.locator('[name=hero_title_uk]')});const content=await contentForm.evaluate(f=>Object.fromEntries(new FormData(f)));
  content.hero_title_uk='Тест $1 «тонування»';content.working_hours_saturday_ru='Сб: 11:00 - 16:00';
  let response=await page.request.post(origin+'/admin/content.php',{form:content});assert(response.status()<400,'Content save');
  homepage=await(await page.request.get(origin+'/')).text();assert(homepage.includes('Тест $1 «тонування»'),'Literal hero saved');assert(!/\sstyle\s*=/.test(homepage),'Content publishing must preserve external CSS');
  const translations=await(await page.request.get(origin+'/langs/ru.json')).json();assert.equal(translations.footer.working_hours_ru.saturday,'Сб: 11:00 - 16:00');
  await post('gallery.php',{action:'update_site'});await post('films.php',{action:'update_site'});
  await post('seo-auto.php',{action:'check_seo'});await post('seo-auto.php',{action:'update_sitemap'});
  await post('seo-auto.php',{action:'save_meta','meta[uk][title]':'SEO $1 «Україна» & вікна','meta[ru][title]':'SEO $1 Украина','meta[uk][description]':'Перевірка SEO опису','meta[ru][description]':'Проверка SEO описания'});
  homepage=await(await page.request.get(origin+'/')).text();assert(homepage.includes('SEO $1 «Україна» &amp; вікна'));
  await open('settings.php');assert.equal(await page.locator('[name=site_title_uk]').inputValue(),'SEO $1 «Україна» & вікна','Settings show published metadata without double encoding');
  await open('guide.php');const guide=await page.locator('form').evaluate(f=>Object.fromEntries(new FormData(f)));response=await page.request.post(origin+'/admin/guide.php',{form:guide});assert.equal(response.status(),200,'Guide save');
  // Invalid indexes and CSRF fail without performing mutations.
  await open('prices.php');const token=await page.locator('[name=csrf_token]').first().inputValue();response=await page.request.post(origin+'/admin/prices.php',{form:{csrf_token:token,action:'delete',index:'99999'}});assert.equal(response.status(),404);
  response=await page.request.post(origin+'/admin/seo-auto.php',{form:{action:'update_sitemap'}});assert.equal(response.status(),403);
  await open('settings.php');const passwordForm=page.locator('form').filter({has:page.locator('[name=current_password]')});const passwordFields=await passwordForm.evaluate(f=>Object.fromEntries(new FormData(f)));
  response=await page.request.post(origin+'/admin/settings.php',{form:{...passwordFields,current_password:'Audit-Only-12345',new_password:'Audit-New-67890',confirm_password:'Audit-New-67890'}});assert.equal(response.status(),200);assert((await response.text()).includes('Пароль изменён и сохранён'));
  await page.context().clearCookies();await open('index.php');await page.locator('[name=username]').fill('audit');await page.locator('[name=password]').fill('Audit-New-67890');await Promise.all([page.waitForNavigation(),page.locator('button').click()]);assert((await page.locator('body').innerText()).includes('Панель управления'));
  console.log('Admin mutations, publication, stale forms, CSRF, SEO, translations and persisted password passed');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
