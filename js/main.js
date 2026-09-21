// Ukrainian guide text is already present in HTML; retain it for language switching.
document.querySelectorAll('#window-film-guide [data-lang-ru]').forEach(el => {
    if (!el.hasAttribute('data-lang-uk')) el.setAttribute('data-lang-uk', el.textContent);
});

// Статический контент сайта (без загрузки из БД)
let siteData = {};

function updateSiteContent() {
    // Функция вызывается при смене языка
    // Контент обновляется через updateContentFromJSON
}

// FAQ toggle functionality
const questions = document.querySelectorAll('.faq-question');

questions.forEach(question => {
    question.addEventListener('click', () => {
        const answer = question.nextElementSibling;
        
        if (answer.style.display === 'none' || answer.style.display === '') {
            answer.style.display = 'block';
        } else {
            answer.style.display = 'none';
        }
    });
});

// Gallery functionality
let slideIndex = 1;
const images = document.querySelectorAll('.gallery-item img');
const modal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImage');
const span = document.getElementsByClassName('close')[0];

function openModal(img) {
    modal.style.display = "block";
    modalImg.src = img.src;
    modalImg.alt = img.alt;
    slideIndex = Array.from(images).indexOf(img) + 1;
}

function plusSlides(n) {
    showSlides(slideIndex += n);
}

function showSlides(n) {
    if (n > images.length) {slideIndex = 1}
    if (n < 1) {slideIndex = images.length}
    modalImg.src = images[slideIndex-1].src;
    modalImg.alt = images[slideIndex-1].alt;
}

span.onclick = function() {
    modal.style.display = "none";
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(event) {
    if (modal.style.display === "block") {
        if (event.key === 'ArrowLeft') {
            plusSlides(-1);
        } else if (event.key === 'ArrowRight') {
            plusSlides(1);
        } else if (event.key === 'Escape') {
            modal.style.display = "none";
        }
    }
});

// Языковые настройки с JSON поддержкой
document.addEventListener('DOMContentLoaded', function() {
    // Глобальные переменные для переводов
    let translations = {
        uk: null,
        ru: null
    };
    
    // Получаем кнопки переключения языка
    const ukBtn = document.getElementById('uk-lang');
    const ruBtn = document.getElementById('ru-lang');
    
    function removeLegacyLanguageParameter(lang) {
        const url = new URL(window.location.href);
        url.searchParams.delete('lang');
        window.history.replaceState({lang}, '', url.pathname + url.search + url.hash);
    }

    // Устанавливаем по умолчанию украинский язык
    let currentLang = localStorage.getItem('lang') || 'uk';
    
    // Загрузка JSON файлов переводов
    async function loadTranslations() {
        try {
            const [ukResponse, ruResponse] = await Promise.all([
                fetch('langs/uk.json'),
                fetch('langs/ru.json')
            ]);
            
            if (ukResponse.ok && ruResponse.ok) {
                translations.uk = await ukResponse.json();
                translations.ru = await ruResponse.json();
                return true;
            } else {
                console.warn('Не удалось загрузить JSON файлы переводов, используется fallback на data-lang атрибуты');
                return false;
            }
        } catch (error) {
            console.warn('Ошибка загрузки JSON файлов:', error);
            return false;
        }
    }
    
    // Функция обновления контента из JSON
    function updateContentFromJSON(lang) {
        if (!translations[lang]) return;
        
        const t = translations[lang];
        
        // 1. Обновляем навигацию
        const navLinks = {
            '#films': t.nav?.films,
            '#pricing': t.nav?.pricing,
            '#gallery': t.nav?.gallery,
            '#service-areas': t.nav?.cities,
            '#faq': t.nav?.faq,
            '#contacts': t.nav?.contacts
        };
        
        Object.keys(navLinks).forEach(selector => {
            const link = document.querySelector(`a[href="${selector}"]`);
            if (link && navLinks[selector]) {
                link.textContent = navLinks[selector];
                link.setAttribute('data-i18n-loaded', 'true');
            }
        });
        
        // 2. Обновляем Hero секцию
        const heroH1 = document.querySelector('.hero h1');
        if (heroH1 && t.hero?.title) {
            heroH1.textContent = t.hero.title;
            heroH1.setAttribute('data-i18n-loaded', 'true');
        }
        
        const heroP = document.querySelector('.hero p');
        if (heroP && t.hero?.description) {
            heroP.textContent = t.hero.description;
            heroP.setAttribute('data-i18n-loaded', 'true');
        }
        
        // 3. Обновляем Films секцию
        if (t.films && Array.isArray(t.films)) {
            const filmCards = document.querySelectorAll('.film-card');
            filmCards.forEach((card, index) => {
                if (t.films[index]) {
                    const film = t.films[index];
                    
                    // Обновляем заголовок
                    const filmH3 = card.querySelector('h3');
                    if (filmH3 && film.title) {
                        filmH3.textContent = film.title;
                        filmH3.setAttribute('data-i18n-loaded', 'true');
                    }
                    
                    // Обновляем описание
                    const filmP = card.querySelector('.film-content p');
                    if (filmP && film.description) {
                        filmP.textContent = film.description;
                        filmP.setAttribute('data-i18n-loaded', 'true');
                    }
                    
                    // Обновляем alt текст изображения
                    const filmImg = card.querySelector('.film-img');
                    if (filmImg && film.alt) {
                        filmImg.setAttribute('alt', film.alt);
                        filmImg.setAttribute('data-i18n-loaded', 'true');
                    }
                    
                    // Обновляем features (список преимуществ)
                    const featuresUl = card.querySelector('.film-features');
                    if (featuresUl && film.features && Array.isArray(film.features)) {
                        featuresUl.innerHTML = '';
                        film.features.forEach(feature => {
                            const li = document.createElement('li');
                            li.textContent = feature;
                            li.setAttribute('data-i18n-loaded', 'true');
                            featuresUl.appendChild(li);
                        });
                    }
                }
            });
        }
        
        // 4. Обновляем Prices секцию
        if (t.prices && Array.isArray(t.prices)) {
            const priceRows = document.querySelectorAll('.pricing-table tbody tr');
            priceRows.forEach((row, index) => {
                if (t.prices[index]) {
                    const price = t.prices[index];
                    const nameCell = row.querySelector('td:first-child');
                    const priceCell = row.querySelector('td:last-child');
                    
                    if (nameCell && price.name) {
                        nameCell.textContent = price.name;
                        nameCell.setAttribute('data-i18n-loaded', 'true');
                    }
                    
                    if (priceCell && price.price) {
                        priceCell.textContent = price.price;
                        priceCell.setAttribute('data-i18n-loaded', 'true');
                    }
                }
            });
        }
        
        // 5. Обновляем Gallery секцию
        if (t.gallery && Array.isArray(t.gallery)) {
            const galleryItems = document.querySelectorAll('.gallery-item');
            galleryItems.forEach((figure, index) => {
                if (t.gallery[index]) {
                    const galleryItem = t.gallery[index];
                    
                    // Обновляем изображение
                    const img = figure.querySelector('img');
                    if (img) {
                        // Обновляем alt
                        if (galleryItem.alt) {
                            img.setAttribute('alt', galleryItem.alt);
                            img.setAttribute('data-i18n-loaded', 'true');
                        }
                    }
                    
                    // Обновляем figcaption
                    const figcaption = figure.querySelector('figcaption');
                    if (figcaption && galleryItem.title) {
                        figcaption.textContent = galleryItem.title;
                        figcaption.setAttribute('data-i18n-loaded', 'true');
                    }
                }
            });
        }
        
        // 6. Обновляем FAQ
        if (t.faq) {
            const faqItems = document.querySelectorAll('.faq-item');
            faqItems.forEach((item, index) => {
                const questionNum = `faq_${index + 1}`;
                if (t.faq[questionNum]) {
                    const questionEl = item.querySelector('.faq-question');
                    const answerEl = item.querySelector('.faq-answer');
                    
                    if (questionEl && t.faq[questionNum].question) {
                        questionEl.textContent = t.faq[questionNum].question;
                        questionEl.setAttribute('data-i18n-loaded', 'true');
                    }
                    
                    if (answerEl && t.faq[questionNum].answer) {
                        answerEl.textContent = t.faq[questionNum].answer;
                        answerEl.style.whiteSpace = 'pre-line';
                        answerEl.setAttribute('data-i18n-loaded', 'true');
                    }
                }
            });
        }
        
        // 7. Обновляем Contacts секцию
        if (t.contacts) {
            // Обновляем заголовок секции contacts если есть
            const contactsH2 = document.querySelector('#contacts h2');
            if (contactsH2) {
                contactsH2.textContent = t.nav?.contacts || 'Контакти';
                contactsH2.setAttribute('data-i18n-loaded', 'true');
            }
            
            // Обновляем телефон
            const phoneParas = document.querySelectorAll('#contacts p');
            phoneParas.forEach(p => {
                const text = p.textContent.trim();
                if (text.includes('Телефон:') || text.includes('+3 (050)')) {
                    p.textContent = (t.common?.phone_label || 'Телефон:') + ' ' + (t.contacts.phone || '');
                    p.setAttribute('data-i18n-loaded', 'true');
                }
            });
            
            // Обновляем email
            const emailLinks = document.querySelectorAll('#contacts a[href^="mailto:"]');
            emailLinks.forEach(link => {
                if (t.contacts.email) {
                    link.href = 'mailto:' + t.contacts.email;
                    link.textContent = t.contacts.email;
                    link.setAttribute('data-i18n-loaded', 'true');
                }
            });
            
            const emailParas = document.querySelectorAll('#contacts p');
            emailParas.forEach(p => {
                const text = p.textContent.trim();
                if (text.includes('Email:') && p.querySelector('a[href^="mailto:"]')) {
                    const emailLabel = t.common?.email_label || 'Email:';
                    p.innerHTML = emailLabel + ' <a href="mailto:' + t.contacts.email + '">' + t.contacts.email + '</a>';
                    p.setAttribute('data-i18n-loaded', 'true');
                }
            });
            
            // Обновляем адрес
            emailParas.forEach(p => {
                const text = p.textContent.trim();
                if ((text.includes('Адреса:') || text.includes('Адрес:')) && !p.hasAttribute('data-i18n-loaded')) {
                    const addressLabel = t.common?.address_label || 'Адреса:';
                    const address = lang === 'uk' ? t.contacts.address_uk : t.contacts.address_ru;
                    if (address) {
                        p.textContent = addressLabel + ' ' + address;
                        p.setAttribute('data-i18n-loaded', 'true');
                    }
                }
            });
        }
        
        // 8. Обновляем Footer
        if (t.footer) {
            // Обновляем описание в footer
            const footerDesc = document.querySelector('footer .footer-info p');
            if (footerDesc) {
                const desc = lang === 'uk' ? t.footer.description_uk_display : t.footer.description_ru_display;
                if (desc) {
                    footerDesc.textContent = desc;
                    footerDesc.style.whiteSpace = 'pre-line';
                    footerDesc.setAttribute('data-i18n-loaded', 'true');
                }
            }
            
            // Обновляем заголовок контактов в footer
            const footerContactsH3 = document.querySelector('footer .footer-info:last-child h3');
            if (footerContactsH3) {
                footerContactsH3.textContent = t.nav?.contacts || 'Контакти';
                footerContactsH3.setAttribute('data-i18n-loaded', 'true');
            }
            
            // Обновляем рабочие часы если есть элементы
            const workingHoursElements = document.querySelectorAll('footer [data-lang-uk*="Пн-Пт"]');
            if (workingHoursElements.length > 0 && t.footer[`working_hours_${lang}`]) {
                const hours = t.footer[`working_hours_${lang}`];
                const hoursText = `${hours.weekdays}\n${hours.saturday}\n${hours.sunday}`;
                workingHoursElements.forEach(el => {
                    el.textContent = hoursText.replace(/\n/g, ' ');
                    el.setAttribute('data-i18n-loaded', 'true');
                });
            }
        }
        
        // 9. Обновляем заголовки секций из JSON если нужно
        const sectionHeaders = {
            '#films h2': 'Тонування вікон Харків - Наші плівки',
            '#pricing h2': 'Тонування вікон Харків - Ціни',
            '#faq h2': 'Тонування вікон Харків - Часті питання'
        };
        
        // Заголовки секций можно обновить если они есть в JSON (пока оставляем как есть)
    }
    
    // Инициализация языка при первой загрузке (SEO-friendly)
    function initializeLanguage(lang) {
        // Сохраняем выбор языка в localStorage
        localStorage.setItem('lang', lang);
        currentLang = lang;
        
        // Обновляем URL в адресной строке (убираем параметры для единообразия)
        // Если есть параметр lang в URL - убираем его
        if (new URLSearchParams(window.location.search).has('lang')) {
            removeLegacyLanguageParameter(lang);
        }
        
        // Обновляем активную кнопку
        if (lang === 'uk') {
            ukBtn.classList.add('active');
            ruBtn.classList.remove('active');
            document.documentElement.lang = 'uk';
        } else {
            ruBtn.classList.add('active');
            ukBtn.classList.remove('active');
            document.documentElement.lang = 'ru';
        }
        
        // При первой загрузке используем data-lang для SEO-совместимости
        // Поисковые боты увидят правильный контент в HTML
        const elements = document.querySelectorAll(`[data-lang-${lang}]`);
        elements.forEach(el => {
            const translation = el.getAttribute(`data-lang-${lang}`);
            if (translation) {
                if (el.closest('#window-film-guide')) {
                    el.textContent = translation;
                } else if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.value = translation;
                } else if (el.tagName === 'P' && el.querySelector('a')) {
                    const linkElement = el.querySelector('a');
                    const linkHref = linkElement.getAttribute('href');
                    if (linkElement && translation.includes(linkElement.textContent)) {
                        el.innerHTML = translation.replace(linkElement.textContent, `<a href="${linkHref}">${linkElement.textContent}</a>`);
                    } else {
                        const match = translation.match(/Email: (.+@.+\..+)/);
                        if (match && match[1]) {
                            const email = match[1];
                            el.innerHTML = translation.replace(email, `<a href="mailto:${email}">${email}</a>`);
                        } else {
                            el.textContent = translation;
                        }
                    }
                } else {
                    el.textContent = translation;
                }
            }
        });
        
        // Обновляем метатеги (используем data-lang для SEO при первой загрузке)
        updateMetaTags(lang, false);
        
        // Обновляем язык для callback виджета
        updateCallbackWidget(lang);
        
        // Помечаем, что язык уже инициализирован из HTML (для SEO)
        // JSON будет использоваться только при дальнейшем переключении языков
    }
    
    // Функция переключения языка (использует JSON при переключении)
    function switchLanguage(lang) {
        // Сохраняем выбор языка в localStorage
        localStorage.setItem('lang', lang);
        currentLang = lang;
        
        // Обновляем URL в адресной строке (убираем параметры для единообразия)
        // Если есть параметр lang в URL - убираем его
        if (new URLSearchParams(window.location.search).has('lang')) {
            removeLegacyLanguageParameter(lang);
        }
        
        // Обновляем активную кнопку
        if (lang === 'uk') {
            ukBtn.classList.add('active');
            ruBtn.classList.remove('active');
            document.documentElement.lang = 'uk';
        } else {
            ruBtn.classList.add('active');
            ukBtn.classList.remove('active');
            document.documentElement.lang = 'ru';
        }
        
        // При переключении языка используем JSON как основной источник
        // JSON синхронизирован с data-lang, но позволяет более гибкое управление
        if (translations[lang]) {
            updateContentFromJSON(lang);
        }
        
        // Fallback: для элементов, которых нет в JSON структуре, используем data-lang
        // Это гарантирует, что все элементы будут обновлены
        const elements = document.querySelectorAll(`[data-lang-${lang}]:not([data-i18n-loaded])`);
        
        // Переключаем текст для каждого элемента
        elements.forEach(el => {
            const translation = el.getAttribute(`data-lang-${lang}`);
            if (translation) {
                if (el.closest('#window-film-guide')) {
                    el.textContent = translation;
                } else if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.value = translation;
                } else if (el.tagName === 'P' && el.querySelector('a')) {
                    // Обработка абзацев со ссылками
                    const linkElement = el.querySelector('a');
                    const linkHref = linkElement.getAttribute('href');
                    
                    if (linkElement && translation.includes(linkElement.textContent)) {
                        // Сохраняем ссылку и обновляем содержимое
                        el.innerHTML = translation.replace(linkElement.textContent, `<a href="${linkHref}">${linkElement.textContent}</a>`);
                    } else {
                        // Обрабатываем случай, когда email адрес нужно вставить в ссылку
                        const match = translation.match(/Email: (.+@.+\..+)/);
                        if (match && match[1]) {
                            const email = match[1];
                            el.innerHTML = translation.replace(email, `<a href="mailto:${email}">${email}</a>`);
                        } else {
                            el.textContent = translation;
                        }
                    }
                } else {
                    el.textContent = translation;
                }
            }
        });
        
        // Обновляем метатеги для SEO (используем JSON при переключении)
        updateMetaTags(lang, true);
        
        // Обновляем язык для callback виджета
        updateCallbackWidget(lang);
        
        // Обновляем контент при смене языка
        updateSiteContent();
    }
    
    // Функция обновления метатегов
    // useJSON: true - использовать JSON (при переключении), false - использовать data-lang (при инициализации для SEO)
    function updateMetaTags(lang, useJSON = false) {
        useJSON = false; // SEO texts in HTML are explicitly edited in admin.
        const t = useJSON ? translations[lang] : null;
        
        // Обновляем title
        const titleElement = document.querySelector('title');
        if (titleElement) {
            if (useJSON && t && t.hero?.title) {
                titleElement.textContent = t.hero.title + ' | tonirovka.kh.ua';
            } else if (titleElement.hasAttribute('data-lang-' + lang)) {
                titleElement.textContent = titleElement.getAttribute('data-lang-' + lang);
            }
        }
        
        // Обновляем meta description
        const metaDescription = document.querySelector('meta[name="description"]');
        if (metaDescription) {
            if (useJSON && t && t.hero?.description) {
                const desc = t.hero.description.substring(0, 160);
                metaDescription.setAttribute('content', desc);
            } else if (metaDescription.hasAttribute('data-lang-' + lang)) {
                metaDescription.setAttribute('content', metaDescription.getAttribute('data-lang-' + lang));
            }
        }
        
        // Обновляем og:title и og:description
        const ogTitle = document.querySelector('meta[property="og:title"]');
        if (ogTitle) {
            if (useJSON && t && t.hero?.title) {
                ogTitle.setAttribute('content', t.hero.title);
            } else if (ogTitle.hasAttribute('data-lang-' + lang)) {
                ogTitle.setAttribute('content', ogTitle.getAttribute('data-lang-' + lang));
            }
        }
        
        const ogDescription = document.querySelector('meta[property="og:description"]');
        if (ogDescription) {
            if (useJSON && t && t.hero?.description) {
                const desc = t.hero.description.substring(0, 200);
                ogDescription.setAttribute('content', desc);
            } else if (ogDescription.hasAttribute('data-lang-' + lang)) {
                ogDescription.setAttribute('content', ogDescription.getAttribute('data-lang-' + lang));
            }
        }
        
        // Обновляем остальные meta теги с data-lang атрибутами
        document.querySelectorAll('meta[data-lang-' + lang + ']').forEach(meta => {
            if (meta.hasAttribute('content') && !meta.hasAttribute('data-i18n-loaded')) {
                meta.setAttribute('content', meta.getAttribute('data-lang-' + lang));
            }
        });
        
        // Обновляем canonical и hreflang теги
        const canonical = document.querySelector('link[rel="canonical"]');
        if (canonical) {
            // canonical URL всегда указывает на базовый URL без параметров
            // Это правильно для одностраничного сайта с JSON переключением языков
            canonical.setAttribute('href', 'https://tonirovka.kh.ua/');
        }
        
        
        // Обновляем geo.placename для SEO
        const geoPlacename = document.querySelector('meta[name="geo.placename"]');
        if (geoPlacename) {
            geoPlacename.setAttribute('content', lang === 'uk' ? 'Харків' : 'Харьков');
        }
    }
    
    // Функция обновления языка callback виджета
    function updateCallbackWidget(lang) {
        const callbackScript = document.querySelector('script[src*="callback.cityhost.ua"]');
        if (callbackScript) {
            callbackScript.setAttribute('data-lang', lang);
            
            // Если доступен метод CBH.setLang, вызываем его
            if (typeof window.CBH !== 'undefined' && window.CBH.setLang) {
                window.CBH.setLang(lang);
            }
        }
    }
    
    // Обработчики кликов на кнопки языков
    ukBtn.addEventListener('click', function() {
        switchLanguage('uk');
    });
    
    ruBtn.addEventListener('click', function() {
        switchLanguage('ru');
    });
    
    // Проверка URL параметров для определения языка
    const urlParams = new URLSearchParams(window.location.search);
    const langParam = urlParams.get('lang');
    
    // Приоритет: URL параметр > localStorage > язык браузера
    if (langParam === 'uk' || langParam === 'ru') {
        // Если есть параметр lang в URL - сохраняем язык и убираем параметр
        currentLang = langParam;
        localStorage.setItem('lang', currentLang);
        // Убираем параметр из URL для единообразия (одностраничный сайт с JSON)
        removeLegacyLanguageParameter(currentLang);
    } else {
        // Если нет параметра в URL, проверяем localStorage или язык браузера
        const savedLang = localStorage.getItem('lang');
        if (savedLang === 'uk' || savedLang === 'ru') {
            currentLang = savedLang;
        } else {
            // Default matches the server-rendered HTML; explicit saved choice still works.
            currentLang = 'uk';
            localStorage.setItem('lang', currentLang);
        }
    }
    
    // Загружаем переводы и инициализируем язык
    loadTranslations().then(loaded => {
        if (loaded) {
            console.log('✅ JSON файлы переводов успешно загружены');
        }
        // Инициализация языка при загрузке страницы
        // Важно: при первой загрузке используем data-lang для SEO,
        // JSON будет использоваться только при переключении языков
        initializeLanguage(currentLang);
    });
});