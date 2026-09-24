const fs = require('fs'), vm = require('vm'), assert = require('assert');
const code = fs.readFileSync(__dirname+'/../js/contact-stats.js','utf8');
async function run(fallback) {
    const handlers={}, sent=[];let clock=10000;
    const context={URL,URLSearchParams,Map,Date:{now:()=>clock},location:{href:'https://tonirovka.kh.ua/'},navigator:{sendBeacon:(url,body)=>{if(fallback==='throw')throw Error('Unavailable');if(fallback)return false;sent.push({url,body:String(body)});return true;}},fetch:(url,options)=>{sent.push({url,body:String(options.body)});assert.equal(options.keepalive,true);return Promise.reject(Error('Network error'));},document:{addEventListener:(type,fn)=>handlers[type]=fn}};
    vm.runInNewContext(code,context);
    function click(href,place,type='click',overrides={}) {
        const link={href,closest:s=>s==='#floating-contacts'?(place==='floating'?{}:null):s==='footer'?(place==='footer'?{}:null):s==='#contacts'?(place==='contacts'?{}:null):null};
        handlers[type]({isTrusted:true,defaultPrevented:false,type,button:type==='click'?0:1,target:{closest:()=>link},preventDefault:()=>{throw Error('Navigation blocked');},...overrides});
    }
    const urls={facebook:'https://www.facebook.com/group',instagram:'https://www.instagram.com/account',viber:'viber://chat?number=123',telegram:'https://t.me/example',phone:'tel:+380508502040'};
    for(const [service,href] of Object.entries(urls))for(const place of ['contacts','footer','floating']) {click(href,place);assert.equal(sent.at(-1).body,`service=${service}&place=${place}`);}
    assert.equal(sent.length,15);
    click(urls.telegram,'footer');assert.equal(sent.length,15);
    clock+=1001;click(urls.telegram,'footer','auxclick');assert.equal(sent.length,16);
    click(urls.facebook,'contacts','click',{isTrusted:false});
    click(urls.facebook,'contacts','click',{defaultPrevented:true});
    click('https://facebook.com.evil.test/','contacts');click(urls.facebook,'other');
    click(urls.facebook,'contacts','auxclick',{button:2});assert.equal(sent.length,16);
    clock+=1001;click(urls.facebook,'contacts','click',{button:0,detail:0});assert.equal(sent.length,17); // Enter activation
    await new Promise(r=>setImmediate(r));
}
(async()=>{for(const fallback of [false,true,'throw'])await run(fallback);console.log('Client: 5 services × 3 positions, repeats, keyboard/middle clicks, beacon/fetch failures and navigation passed');})().catch(e=>{console.error(e);process.exit(1);});
