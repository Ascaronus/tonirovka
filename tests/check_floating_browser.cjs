const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('assert');
(async()=>{
 const browser = await chromium.launch({headless:true,args:['--no-sandbox']});
 try {
 // A hung third-party callback must not hold DOMContentLoaded or local controls.
 const pending=await browser.newPage({viewport:{width:375,height:850}});
 let releaseCallback;
 const held=new Promise(resolve=>{releaseCallback=resolve;});
 await pending.route('**/*',async route=>{
  if(route.request().url().startsWith('https://callback.cityhost.ua/js/')) {await held;return route.abort();}
  return new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort();
 });
 try {
  await pending.goto('http://127.0.0.1:8080/',{waitUntil:'domcontentloaded',timeout:10000});
  await pending.locator('#floating-contacts').waitFor({state:'visible',timeout:3000});
  const telephone=pending.locator('#floating-contacts .contact-phone-action');
  assert(await telephone.isVisible(),'Phone remains available while callback is pending');
  assert((await telephone.getAttribute('href')).startsWith('tel:+380'),'Published telephone is used');
  await pending.locator('.faq-question').first().click();
  assert(await pending.locator('.faq-answer').first().isVisible(),'FAQ works while callback is pending');
 } finally { releaseCallback();await pending.close(); }
 console.log('Pending callback does not block DOMContentLoaded, contacts or FAQ');
 for (const width of [320,375,390,768,1440]) {
  const page=await browser.newPage({viewport:{width,height:850},isMobile:width<600,hasTouch:width<600});
  await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
  await page.goto('http://127.0.0.1:8080/',{waitUntil:'domcontentloaded'});
  const bar=page.locator('#floating-contacts');await bar.waitFor({state:'visible'});
  assert.equal(await bar.locator('a').count(),5);
  if(width>600){const box=await bar.boundingBox();assert(Math.abs(box.x+box.width/2-width/2)<1,'Desktop centered');assert.equal((await bar.locator('img').first().boundingBox()).width,60);}else{assert.equal((await bar.locator('img').first().boundingBox()).width,36);}
  for(const link of await bar.locator('a').all()) {
   const b=await link.boundingBox();assert(b.width>=44 && b.height>=44 && b.x>=0 && b.x+b.width<=width);
   assert(await link.getAttribute('aria-label'));
   if (!(await link.getAttribute('href')).startsWith('tel:')) {
    assert.equal(await link.getAttribute('target'),'_blank');
    assert((await link.getAttribute('rel')).includes('noopener'));
   }
  }
  if(width===1440){
   const guide=page.locator('#window-film-guide article p').first();
   const uk=await guide.textContent(),ru=await guide.getAttribute('data-lang-ru');
   await page.locator('#ru-lang').click();
   await page.waitForFunction(text=>document.querySelector('#window-film-guide article p').textContent===text,ru);
   await page.locator('#uk-lang').click();
   await page.waitForFunction(text=>document.querySelector('#window-film-guide article p').textContent===text,uk);

   await page.emulateMedia({media:'print'});
   assert(await page.locator('header').isHidden(),'Hide navigation in print');
   assert(await bar.isHidden(),'Hide floating contacts in print');
   assert(await page.locator('.faq-answer').first().isVisible(),'Expand FAQ in print');
   assert(await page.locator('#contacts .contact-details').isVisible(),'Keep contact details in print');
   await page.emulateMedia({media:'screen'});
  }
  await page.evaluate(()=>{const callback=document.createElement('button');callback.id='cbch_modal_callback_button';callback.style.cssText='position:fixed;bottom:16px;left:8px;width:180px;height:70px;z-index:1000';if(window.innerWidth>600){callback.style.left='50%';callback.style.transform='translateX(-50%)';}callback.textContent='Callback fixture';document.body.append(callback);});
  await page.waitForTimeout(150);
  assert(await bar.locator('.contact-phone-action').isVisible(),'Direct calling remains next to messengers with provider available');
  const a=await bar.boundingBox(),b=await page.locator('#cbch_modal_callback_button').boundingBox();assert(a.y+a.height<=b.y-10,'Callback collision at '+width);
  if(width===1440 || width===375){
   const originalUrl=page.url();
   await page.evaluate(()=>document.querySelector('#contacts a[href*="facebook.com"]').href=location.origin+'/?contact-test=1');
   await page.waitForFunction(()=>document.querySelector('#floating-contacts a[aria-label="Facebook"]').href.includes('contact-test=1'));
   const popupPromise=page.context().waitForEvent('page');
   await bar.locator('a[aria-label="Facebook"]').click();
   const popup=await popupPromise;await popup.waitForLoadState('domcontentloaded');
   assert.equal(page.url(),originalUrl,'Original site remains open');
   assert(popup.url().includes('contact-test=1'),'Contact opens in a new tab');
   await popup.close();
  }
  const response=page.waitForResponse(r=>r.url().endsWith('/contact-click.php')&&r.request().method()==='POST');
  await page.evaluate(()=>document.addEventListener('click',e=>{if(e.target.closest('#floating-contacts a'))e.preventDefault();}));
  await bar.locator('a[aria-label="Telegram"]').click();
  const click=await response;assert.equal(click.status(),204);assert(click.request().postData().includes('place=floating'));
  await page.evaluate(()=>document.querySelector('#contacts a[href*="instagram.com"]').href='https://www.instagram.com/updated-account');
  await page.waitForFunction(()=>document.querySelector('#floating-contacts a[aria-label="Instagram"]').href.includes('updated-account'));
  await page.locator('#contacts .contacts-container').scrollIntoViewIfNeeded();await page.waitForFunction(()=>document.querySelector('#floating-contacts').hidden);
  await page.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await page.waitForTimeout(100);assert(await bar.isHidden());
  assert(await page.locator('footer .contact-phone-action').isVisible(),'Footer has its own direct call');
  assert.equal(await page.locator('footer .contact-phone-action').getAttribute('href'),await bar.locator('.contact-phone-action').getAttribute('href')); 
  await page.evaluate(()=>window.scrollTo(0,0));await bar.waitFor({state:'visible'});
  await page.evaluate(()=>{const overlay=document.createElement('div');overlay.id='cbch_modal_callback_background';document.body.append(overlay);});await page.waitForFunction(()=>document.querySelector('#floating-contacts').hidden);
  await page.evaluate(()=>document.getElementById('cbch_modal_callback_background').remove());await bar.waitFor({state:'visible'});
  await page.close();console.log('Floating contacts: '+width+'px, touch targets, callback collision, links, stats and scroll passed');
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
