const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('assert');
(async()=>{
 const browser = await chromium.launch({headless:true,args:['--no-sandbox']});
 try {
 for (const width of [320,375,390,768,1440]) {
  const page=await browser.newPage({viewport:{width,height:850},isMobile:width<600,hasTouch:width<600});
  await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
  await page.goto('http://127.0.0.1:8080/',{waitUntil:'domcontentloaded'});
  const bar=page.locator('#floating-contacts');await bar.waitFor({state:'visible'});
  assert.equal(await bar.locator('a').count(),4);
  if(width>600){const box=await bar.boundingBox();assert(Math.abs(box.x+box.width/2-width/2)<1,'Desktop centered');assert.equal((await bar.locator('img').first().boundingBox()).width,60);}else{assert.equal((await bar.locator('img').first().boundingBox()).width,36);}
  for(const link of await bar.locator('a').all()) {
   const b=await link.boundingBox();assert(b.width>=44 && b.height>=44 && b.x>=0 && b.x+b.width<=width);
   assert(await link.getAttribute('aria-label'));
  }
  if(width===1440){
   await page.emulateMedia({media:'print'});
   assert(await page.locator('header').isHidden(),'Hide navigation in print');
   assert(await bar.isHidden(),'Hide floating contacts in print');
   assert(await page.locator('.faq-answer').first().isVisible(),'Expand FAQ in print');
   assert(await page.locator('#contacts .contact-details').isVisible(),'Keep contact details in print');
   await page.emulateMedia({media:'screen'});
  }
  await page.evaluate(()=>{const callback=document.createElement('button');callback.id='cbch_modal_callback_button';callback.style.cssText='position:fixed;bottom:16px;left:8px;width:180px;height:70px;z-index:1000';if(window.innerWidth>600){callback.style.left='50%';callback.style.transform='translateX(-50%)';}callback.textContent='Callback fixture';document.body.append(callback);});
  await page.waitForTimeout(150);
  const a=await bar.boundingBox(),b=await page.locator('#cbch_modal_callback_button').boundingBox();assert(a.y+a.height<=b.y-10,'Callback collision at '+width);
  const response=page.waitForResponse(r=>r.url().endsWith('/contact-click.php')&&r.request().method()==='POST');
  await page.evaluate(()=>document.addEventListener('click',e=>{if(e.target.closest('#floating-contacts a'))e.preventDefault();}));
  await bar.locator('a[aria-label="Telegram"]').click();
  const click=await response;assert.equal(click.status(),204);assert(click.request().postData().includes('place=floating'));
  await page.evaluate(()=>document.querySelector('#contacts a[href*="instagram.com"]').href='https://www.instagram.com/updated-account');
  await page.waitForFunction(()=>document.querySelector('#floating-contacts a[aria-label="Instagram"]').href.includes('updated-account'));
  await page.locator('#contacts .contacts-container').scrollIntoViewIfNeeded();await page.waitForFunction(()=>document.querySelector('#floating-contacts').hidden);
  await page.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await page.waitForTimeout(100);assert(await bar.isHidden());
  await page.evaluate(()=>window.scrollTo(0,0));await bar.waitFor({state:'visible'});
  await page.evaluate(()=>{const overlay=document.createElement('div');overlay.id='cbch_modal_callback_background';document.body.append(overlay);});await page.waitForFunction(()=>document.querySelector('#floating-contacts').hidden);
  await page.evaluate(()=>document.getElementById('cbch_modal_callback_background').remove());await bar.waitFor({state:'visible'});
  await page.close();console.log('Floating contacts: '+width+'px, touch targets, callback collision, links, stats and scroll passed');
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
