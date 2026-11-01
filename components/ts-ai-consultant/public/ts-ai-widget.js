/* ==========================================================================
   ts-ai-consultant – widget textového AI konzultanta (bez závislostí)
   Vložení:  <link rel="stylesheet" href="/vendor/ts/ts-ai-consultant/public/ts-ai-widget.css">
             <script>window.TS_AI_CONFIG = { api: '/api/ai/chat.php', business: 'T&S Studio', email: 'info@…', privacyUrl: { cs: '/cs/ochrana-osobnich-udaju.html', '*': '/privacy.html' } };</script>
             <script src="/vendor/ts/ts-ai-consultant/public/ts-ai-widget.js" defer></script>
   Jazyk: TS_AI_CONFIG.lang, jinak <html lang>; návštěvník ho může přepnout v hlavičce panelu.
   API klíč ani znalosti v prohlížeči nejsou; widget zná jen adresu brány. Každá chyba (výpadek, limit,
   rozpočet) skončí textem s kontaktem, web se nezastaví.
   ========================================================================== */
(function () {
  'use strict';
  try {
    if (window.__tsAiWidget) return; window.__tsAiWidget = true;
    var C = window.TS_AI_CONFIG || {};
    var API = C.api || '/api/ai/chat.php';
    var LANGS = ['en', 'cs', 'de', 'es', 'fr', 'pt', 'it', 'pl', 'nl', 'uk', 'ar', 'hi', 'zh', 'ja', 'ko', 'tr', 'id', 'ru'];
    var NAMES = { en: 'English', cs: 'Čeština', de: 'Deutsch', es: 'Español', fr: 'Français', pt: 'Português', it: 'Italiano', pl: 'Polski', nl: 'Nederlands', uk: 'Українська', ar: 'العربية', hi: 'हिन्दी', zh: '简体中文', ja: '日本語', ko: '한국어', tr: 'Türkçe', id: 'Bahasa Indonesia', ru: 'Русский' };
    /* klíče: open, intro, note, ph, send, close, lang, qNew, mNew, qImp, mImp, qRef, mRef, qPrice, mPrice, qContact, typing, err, fTitle, name, email, phone, msg, consent, privacy, submit, sent, fErr, newChat, cancel, limit, you, ai */
    var T = {
      en: ['AI assistant', 'Hello, I\'m the AI assistant of {b}. I can help you choose a service, show similar projects and connect you with the studio.', 'You are talking to an AI, it can make mistakes. Please don\'t share sensitive information in the chat.', 'Write a message…', 'Send', 'Close', 'Language', 'New website', 'I want a new website. Where do we start?', 'Improve my website', 'I want to improve my current website.', 'Portfolio', 'Show me similar projects from your portfolio.', 'Estimated price', 'Roughly how much would a website cost?', 'Contact the studio', 'The assistant is typing…', 'The connection failed. Please write to us at {e}.', 'Contact the studio', 'Name', 'Email', 'Phone (optional)', 'Message', 'I agree to the processing of the data from this form and this conversation so the studio can reply to me.', 'Privacy policy', 'Send enquiry', 'Thank you, your enquiry has been sent. One of us will get back to you.', 'Please fill in your name, a valid email and the consent.', 'New conversation', 'Cancel', 'Maximum {n} characters.', 'You', 'Assistant'],
      cs: ['AI asistent', 'Dobrý den, jsem AI asistent {b}. Pomůžu vám s výběrem služby, ukážu podobné projekty a spojím vás se studiem.', 'Mluvíte s AI, může se splést. Nepište prosím do chatu citlivé údaje.', 'Napište zprávu…', 'Odeslat', 'Zavřít', 'Jazyk', 'Nový web', 'Chci nový web. Jak začít?', 'Zlepšit můj web', 'Chci vylepšit svůj stávající web.', 'Reference', 'Ukažte mi podobné projekty z vašich referencí.', 'Orientační cena', 'Kolik by mě orientačně stál web?', 'Kontaktovat studio', 'Asistent píše…', 'Spojení se nepodařilo. Napište nám prosím na {e}.', 'Kontakt na studio', 'Jméno', 'E-mail', 'Telefon (nepovinné)', 'Zpráva', 'Souhlasím se zpracováním údajů z formuláře a této konverzace, aby mi studio mohlo odpovědět.', 'Zásady ochrany osobních údajů', 'Odeslat poptávku', 'Děkujeme, poptávka je odeslaná. Ozve se vám jeden z nás.', 'Vyplňte prosím jméno, platný e-mail a souhlas.', 'Nová konverzace', 'Zrušit', 'Nejvýš {n} znaků.', 'Vy', 'Asistent'],
      de: ['KI-Assistent', 'Hallo, ich bin der KI-Assistent von {b}. Ich helfe bei der Wahl der Leistung, zeige ähnliche Projekte und verbinde Sie mit dem Studio.', 'Sie sprechen mit einer KI, sie kann sich irren. Bitte teilen Sie keine sensiblen Daten im Chat.', 'Nachricht schreiben…', 'Senden', 'Schließen', 'Sprache', 'Neue Website', 'Ich möchte eine neue Website. Wie fangen wir an?', 'Website verbessern', 'Ich möchte meine bestehende Website verbessern.', 'Referenzen', 'Zeigen Sie mir ähnliche Projekte aus Ihren Referenzen.', 'Preisrahmen', 'Was würde eine Website ungefähr kosten?', 'Studio kontaktieren', 'Der Assistent schreibt…', 'Die Verbindung ist fehlgeschlagen. Schreiben Sie uns bitte an {e}.', 'Kontakt zum Studio', 'Name', 'E-Mail', 'Telefon (optional)', 'Nachricht', 'Ich stimme der Verarbeitung der Daten aus diesem Formular und diesem Gespräch zu, damit das Studio mir antworten kann.', 'Datenschutzerklärung', 'Anfrage senden', 'Danke, Ihre Anfrage ist verschickt. Einer von uns meldet sich bei Ihnen.', 'Bitte Name, gültige E-Mail und Einwilligung ausfüllen.', 'Neues Gespräch', 'Abbrechen', 'Höchstens {n} Zeichen.', 'Sie', 'Assistent'],
      es: ['Asistente IA', 'Hola, soy el asistente IA de {b}. Te ayudo a elegir un servicio, te muestro proyectos parecidos y te pongo en contacto con el estudio.', 'Estás hablando con una IA y puede equivocarse. No compartas datos sensibles en el chat.', 'Escribe un mensaje…', 'Enviar', 'Cerrar', 'Idioma', 'Web nueva', 'Quiero una web nueva. ¿Por dónde empezamos?', 'Mejorar mi web', 'Quiero mejorar mi web actual.', 'Portfolio', 'Muéstrame proyectos parecidos de vuestro portfolio.', 'Precio orientativo', '¿Cuánto costaría una web aproximadamente?', 'Contactar con el estudio', 'El asistente está escribiendo…', 'La conexión ha fallado. Escríbenos a {e}.', 'Contacto con el estudio', 'Nombre', 'Correo electrónico', 'Teléfono (opcional)', 'Mensaje', 'Acepto el tratamiento de los datos de este formulario y de esta conversación para que el estudio pueda responderme.', 'Política de privacidad', 'Enviar consulta', 'Gracias, tu consulta se ha enviado. Uno de nosotros te responderá.', 'Rellena tu nombre, un correo válido y el consentimiento.', 'Nueva conversación', 'Cancelar', 'Máximo {n} caracteres.', 'Tú', 'Asistente'],
      fr: ['Assistant IA', 'Bonjour, je suis l\'assistant IA de {b}. Je vous aide à choisir un service, je vous montre des projets similaires et je vous mets en relation avec le studio.', 'Vous parlez à une IA, elle peut se tromper. Ne partagez pas de données sensibles dans le chat.', 'Écrivez un message…', 'Envoyer', 'Fermer', 'Langue', 'Nouveau site', 'Je veux un nouveau site. Par où commencer ?', 'Améliorer mon site', 'Je veux améliorer mon site actuel.', 'Références', 'Montrez-moi des projets similaires de vos références.', 'Prix indicatif', 'Combien coûterait un site, à peu près ?', 'Contacter le studio', 'L\'assistant écrit…', 'La connexion a échoué. Écrivez-nous à {e}.', 'Contacter le studio', 'Nom', 'E-mail', 'Téléphone (facultatif)', 'Message', 'J\'accepte le traitement des données de ce formulaire et de cette conversation afin que le studio puisse me répondre.', 'Politique de confidentialité', 'Envoyer la demande', 'Merci, votre demande est envoyée. L\'un de nous vous répondra.', 'Indiquez votre nom, un e-mail valide et votre consentement.', 'Nouvelle conversation', 'Annuler', '{n} caractères maximum.', 'Vous', 'Assistant'],
      pt: ['Assistente IA', 'Olá, sou o assistente IA do {b}. Ajudo a escolher um serviço, mostro projetos parecidos e ponho-o em contacto com o estúdio.', 'Está a falar com uma IA, que pode errar. Não partilhe dados sensíveis no chat.', 'Escreva uma mensagem…', 'Enviar', 'Fechar', 'Idioma', 'Novo site', 'Quero um site novo. Por onde começamos?', 'Melhorar o meu site', 'Quero melhorar o meu site atual.', 'Portfólio', 'Mostre-me projetos parecidos do vosso portfólio.', 'Preço indicativo', 'Quanto custaria um site, aproximadamente?', 'Contactar o estúdio', 'O assistente está a escrever…', 'A ligação falhou. Escreva-nos para {e}.', 'Contacto com o estúdio', 'Nome', 'E-mail', 'Telefone (opcional)', 'Mensagem', 'Aceito o tratamento dos dados deste formulário e desta conversa para que o estúdio me possa responder.', 'Política de privacidade', 'Enviar pedido', 'Obrigado, o seu pedido foi enviado. Um de nós entrará em contacto.', 'Preencha o nome, um e-mail válido e o consentimento.', 'Nova conversa', 'Cancelar', 'Máximo de {n} caracteres.', 'Você', 'Assistente'],
      it: ['Assistente IA', 'Ciao, sono l\'assistente IA di {b}. Ti aiuto a scegliere un servizio, ti mostro progetti simili e ti metto in contatto con lo studio.', 'Stai parlando con un\'IA, può sbagliare. Non condividere dati sensibili nella chat.', 'Scrivi un messaggio…', 'Invia', 'Chiudi', 'Lingua', 'Nuovo sito', 'Voglio un nuovo sito. Da dove iniziamo?', 'Migliorare il mio sito', 'Voglio migliorare il mio sito attuale.', 'Lavori', 'Mostrami progetti simili tra i vostri lavori.', 'Prezzo indicativo', 'Quanto costerebbe all\'incirca un sito?', 'Contatta lo studio', 'L\'assistente sta scrivendo…', 'Connessione non riuscita. Scrivici a {e}.', 'Contatta lo studio', 'Nome', 'E-mail', 'Telefono (facoltativo)', 'Messaggio', 'Acconsento al trattamento dei dati di questo modulo e di questa conversazione affinché lo studio possa rispondermi.', 'Informativa sulla privacy', 'Invia richiesta', 'Grazie, la richiesta è stata inviata. Uno di noi ti risponderà.', 'Inserisci nome, un\'e-mail valida e il consenso.', 'Nuova conversazione', 'Annulla', 'Massimo {n} caratteri.', 'Tu', 'Assistente'],
      pl: ['Asystent AI', 'Dzień dobry, jestem asystentem AI {b}. Pomogę wybrać usługę, pokażę podobne projekty i połączę z naszym studiem.', 'Rozmawiasz z AI, która może się mylić. Nie podawaj w czacie wrażliwych danych.', 'Napisz wiadomość…', 'Wyślij', 'Zamknij', 'Język', 'Nowa strona', 'Chcę nową stronę. Od czego zacząć?', 'Ulepszyć moją stronę', 'Chcę ulepszyć moją obecną stronę.', 'Portfolio', 'Pokaż mi podobne projekty z waszego portfolio.', 'Orientacyjna cena', 'Ile mniej więcej kosztowałaby strona?', 'Kontakt ze studiem', 'Asystent pisze…', 'Nie udało się połączyć. Napisz do nas na {e}.', 'Kontakt ze studiem', 'Imię', 'E-mail', 'Telefon (opcjonalnie)', 'Wiadomość', 'Zgadzam się na przetwarzanie danych z tego formularza i tej rozmowy, aby studio mogło mi odpowiedzieć.', 'Polityka prywatności', 'Wyślij zapytanie', 'Dziękujemy, zapytanie zostało wysłane. Odezwie się jedno z nas.', 'Podaj imię, poprawny e-mail i zgodę.', 'Nowa rozmowa', 'Anuluj', 'Maksymalnie {n} znaków.', 'Ty', 'Asystent'],
      nl: ['AI-assistent', 'Hallo, ik ben de AI-assistent van {b}. Ik help je een dienst te kiezen, laat vergelijkbare projecten zien en breng je in contact met de studio.', 'Je praat met een AI, die kan zich vergissen. Deel geen gevoelige gegevens in de chat.', 'Schrijf een bericht…', 'Versturen', 'Sluiten', 'Taal', 'Nieuwe website', 'Ik wil een nieuwe website. Waar beginnen we?', 'Mijn website verbeteren', 'Ik wil mijn huidige website verbeteren.', 'Portfolio', 'Laat me vergelijkbare projecten uit jullie portfolio zien.', 'Prijsindicatie', 'Wat zou een website ongeveer kosten?', 'Contact met de studio', 'De assistent typt…', 'De verbinding is mislukt. Mail ons op {e}.', 'Contact met de studio', 'Naam', 'E-mail', 'Telefoon (optioneel)', 'Bericht', 'Ik ga akkoord met de verwerking van de gegevens uit dit formulier en dit gesprek, zodat de studio mij kan antwoorden.', 'Privacybeleid', 'Aanvraag versturen', 'Bedankt, je aanvraag is verstuurd. Een van ons neemt contact op.', 'Vul je naam, een geldig e-mailadres en de toestemming in.', 'Nieuw gesprek', 'Annuleren', 'Maximaal {n} tekens.', 'Jij', 'Assistent'],
      uk: ['AI-асистент', 'Вітаю, я AI-асистент {b}. Допоможу вибрати послугу, покажу схожі проєкти та зв\'яжу вас зі студією.', 'Ви спілкуєтеся з AI, він може помилятися. Не діліться в чаті чутливими даними.', 'Напишіть повідомлення…', 'Надіслати', 'Закрити', 'Мова', 'Новий сайт', 'Хочу новий сайт. З чого почати?', 'Покращити мій сайт', 'Хочу покращити свій нинішній сайт.', 'Портфоліо', 'Покажіть схожі проєкти з вашого портфоліо.', 'Орієнтовна ціна', 'Скільки орієнтовно коштуватиме сайт?', 'Зв\'язатися зі студією', 'Асистент пише…', 'Не вдалося з\'єднатися. Напишіть нам на {e}.', 'Контакт зі студією', 'Ім\'я', 'E-mail', 'Телефон (необов\'язково)', 'Повідомлення', 'Погоджуюся на обробку даних із цієї форми та цієї розмови, щоб студія могла мені відповісти.', 'Політика конфіденційності', 'Надіслати запит', 'Дякуємо, запит надіслано. Один із нас вам відповість.', 'Заповніть ім\'я, коректний e-mail і згоду.', 'Нова розмова', 'Скасувати', 'Не більше {n} символів.', 'Ви', 'Асистент'],
      ar: ['مساعد ذكاء اصطناعي', 'مرحبًا، أنا مساعد الذكاء الاصطناعي لدى {b}. أساعدك في اختيار الخدمة وأعرض مشاريع مشابهة وأصلك بالاستوديو.', 'أنت تتحدث مع ذكاء اصطناعي وقد يخطئ. لا تشارك بيانات حساسة في المحادثة.', 'اكتب رسالة…', 'إرسال', 'إغلاق', 'اللغة', 'موقع جديد', 'أريد موقعًا جديدًا. من أين نبدأ؟', 'تحسين موقعي', 'أريد تحسين موقعي الحالي.', 'أعمالنا', 'اعرض عليّ مشاريع مشابهة من أعمالكم.', 'سعر تقريبي', 'كم قد يكلف الموقع تقريبًا؟', 'التواصل مع الاستوديو', 'المساعد يكتب…', 'تعذر الاتصال. يرجى مراسلتنا على {e}.', 'التواصل مع الاستوديو', 'الاسم', 'البريد الإلكتروني', 'الهاتف (اختياري)', 'الرسالة', 'أوافق على معالجة بيانات هذا النموذج وهذه المحادثة ليتمكن الاستوديو من الرد علي.', 'سياسة الخصوصية', 'إرسال الطلب', 'شكرًا، تم إرسال طلبك. سيتواصل معك أحدنا.', 'يرجى إدخال الاسم وبريد إلكتروني صحيح والموافقة.', 'محادثة جديدة', 'إلغاء', 'الحد الأقصى {n} حرف.', 'أنت', 'المساعد'],
      hi: ['AI सहायक', 'नमस्ते, मैं {b} का AI सहायक हूँ। मैं सेवा चुनने में मदद करूँगा, मिलते-जुलते प्रोजेक्ट दिखाऊँगा और आपको स्टूडियो से जोड़ूँगा।', 'आप AI से बात कर रहे हैं, यह गलती कर सकता है। कृपया चैट में संवेदनशील जानकारी न लिखें।', 'संदेश लिखें…', 'भेजें', 'बंद करें', 'भाषा', 'नई वेबसाइट', 'मुझे नई वेबसाइट चाहिए। कहाँ से शुरू करें?', 'मेरी वेबसाइट बेहतर करें', 'मैं अपनी मौजूदा वेबसाइट बेहतर करना चाहता हूँ।', 'पोर्टफ़ोलियो', 'अपने पोर्टफ़ोलियो से मिलते-जुलते प्रोजेक्ट दिखाइए।', 'अनुमानित कीमत', 'एक वेबसाइट लगभग कितने की होगी?', 'स्टूडियो से संपर्क करें', 'सहायक लिख रहा है…', 'कनेक्शन विफल रहा। कृपया हमें {e} पर लिखें।', 'स्टूडियो से संपर्क', 'नाम', 'ईमेल', 'फ़ोन (वैकल्पिक)', 'संदेश', 'मैं सहमत हूँ कि स्टूडियो मुझे जवाब देने के लिए इस फ़ॉर्म और इस बातचीत का डेटा संसाधित करे।', 'गोपनीयता नीति', 'अनुरोध भेजें', 'धन्यवाद, आपका अनुरोध भेज दिया गया है। हम में से कोई आपसे संपर्क करेगा।', 'कृपया नाम, सही ईमेल और सहमति भरें।', 'नई बातचीत', 'रद्द करें', 'अधिकतम {n} अक्षर।', 'आप', 'सहायक'],
      zh: ['AI 助手', '您好，我是 {b} 的 AI 助手。我可以帮您选择服务、展示相似项目，并为您联系工作室。', '您正在与 AI 对话，它可能会出错。请不要在聊天中填写敏感信息。', '输入消息…', '发送', '关闭', '语言', '新网站', '我想做一个新网站。从哪里开始？', '改进我的网站', '我想改进现有的网站。', '作品', '给我看看你们作品中相似的项目。', '大致价格', '一个网站大概需要多少钱？', '联系工作室', '助手正在输入…', '连接失败。请发邮件至 {e}。', '联系工作室', '姓名', '电子邮箱', '电话（选填）', '留言', '我同意处理此表单和本次对话中的数据，以便工作室回复我。', '隐私政策', '发送咨询', '谢谢，您的咨询已发送。我们会有人与您联系。', '请填写姓名、有效邮箱并勾选同意。', '新对话', '取消', '最多 {n} 个字符。', '您', '助手'],
      ja: ['AIアシスタント', 'こんにちは、{b} のAIアシスタントです。サービス選び、似たプロジェクトのご紹介、スタジオへのご連絡をお手伝いします。', 'AIと会話しています。間違えることがあります。チャットに機密情報は書かないでください。', 'メッセージを入力…', '送信', '閉じる', '言語', '新しいサイト', '新しいサイトを作りたいです。何から始めればいいですか？', 'サイトを改善', '今のサイトを改善したいです。', '実績', '実績の中から似たプロジェクトを見せてください。', '目安の料金', 'サイトはだいたいいくらくらいですか？', 'スタジオに連絡', 'アシスタントが入力中…', '接続できませんでした。{e} までご連絡ください。', 'スタジオへのお問い合わせ', 'お名前', 'メールアドレス', '電話番号（任意）', 'メッセージ', 'スタジオから返信を受けるため、このフォームと会話のデータの処理に同意します。', 'プライバシーポリシー', '問い合わせを送信', 'ありがとうございます。送信しました。担当者からご連絡します。', 'お名前、正しいメールアドレス、同意を入力してください。', '新しい会話', 'キャンセル', '最大 {n} 文字です。', 'あなた', 'アシスタント'],
      ko: ['AI 어시스턴트', '안녕하세요, {b}의 AI 어시스턴트입니다. 서비스 선택을 돕고 비슷한 프로젝트를 보여 드리며 스튜디오와 연결해 드립니다.', 'AI와 대화 중이며 실수할 수 있습니다. 채팅에 민감한 정보를 쓰지 마세요.', '메시지를 입력하세요…', '보내기', '닫기', '언어', '새 웹사이트', '새 웹사이트를 만들고 싶어요. 무엇부터 시작하나요?', '내 웹사이트 개선', '지금 웹사이트를 개선하고 싶어요.', '포트폴리오', '포트폴리오에서 비슷한 프로젝트를 보여 주세요.', '대략적인 가격', '웹사이트는 대략 얼마인가요?', '스튜디오에 문의', '어시스턴트가 입력 중…', '연결에 실패했습니다. {e}(으)로 메일을 보내 주세요.', '스튜디오 문의', '이름', '이메일', '전화번호(선택)', '메시지', '스튜디오가 답변할 수 있도록 이 양식과 대화의 데이터 처리에 동의합니다.', '개인정보 처리방침', '문의 보내기', '감사합니다. 문의가 전송되었습니다. 곧 연락드리겠습니다.', '이름, 올바른 이메일, 동의를 입력해 주세요.', '새 대화', '취소', '최대 {n}자입니다.', '나', '어시스턴트'],
      tr: ['Yapay zekâ asistanı', 'Merhaba, ben {b} yapay zekâ asistanıyım. Hizmet seçmenize yardım eder, benzer projeleri gösterir ve sizi stüdyoyla buluştururum.', 'Bir yapay zekâ ile konuşuyorsunuz, hata yapabilir. Lütfen sohbete hassas bilgiler yazmayın.', 'Mesaj yazın…', 'Gönder', 'Kapat', 'Dil', 'Yeni web sitesi', 'Yeni bir web sitesi istiyorum. Nereden başlayalım?', 'Sitemi iyileştir', 'Mevcut web sitemi iyileştirmek istiyorum.', 'Portföy', 'Portföyünüzden benzer projeleri gösterin.', 'Tahmini fiyat', 'Bir web sitesi yaklaşık ne kadar tutar?', 'Stüdyoyla iletişim', 'Asistan yazıyor…', 'Bağlantı kurulamadı. Lütfen {e} adresine yazın.', 'Stüdyoyla iletişim', 'Ad', 'E-posta', 'Telefon (isteğe bağlı)', 'Mesaj', 'Stüdyonun bana yanıt verebilmesi için bu formdaki ve bu sohbetteki verilerin işlenmesini kabul ediyorum.', 'Gizlilik politikası', 'Talebi gönder', 'Teşekkürler, talebiniz gönderildi. Birimiz size dönecek.', 'Lütfen adınızı, geçerli bir e-postayı ve onayı doldurun.', 'Yeni sohbet', 'İptal', 'En fazla {n} karakter.', 'Siz', 'Asistan'],
      id: ['Asisten AI', 'Halo, saya asisten AI {b}. Saya bisa membantu memilih layanan, menunjukkan proyek serupa, dan menghubungkan Anda dengan studio.', 'Anda sedang berbicara dengan AI yang bisa keliru. Jangan menulis data sensitif di chat.', 'Tulis pesan…', 'Kirim', 'Tutup', 'Bahasa', 'Website baru', 'Saya ingin website baru. Mulai dari mana?', 'Perbaiki website saya', 'Saya ingin memperbaiki website saya yang sekarang.', 'Portofolio', 'Tunjukkan proyek serupa dari portofolio Anda.', 'Perkiraan harga', 'Kira-kira berapa biaya sebuah website?', 'Hubungi studio', 'Asisten sedang mengetik…', 'Koneksi gagal. Silakan kirim email ke {e}.', 'Hubungi studio', 'Nama', 'Email', 'Telepon (opsional)', 'Pesan', 'Saya setuju data dari formulir dan percakapan ini diproses agar studio dapat membalas saya.', 'Kebijakan privasi', 'Kirim permintaan', 'Terima kasih, permintaan Anda sudah terkirim. Salah satu dari kami akan menghubungi Anda.', 'Isi nama, email yang valid, dan persetujuan.', 'Percakapan baru', 'Batal', 'Maksimal {n} karakter.', 'Anda', 'Asisten'],
      ru: ['AI-ассистент', 'Здравствуйте, я AI-ассистент {b}. Помогу выбрать услугу, покажу похожие проекты и свяжу вас со студией.', 'Вы общаетесь с AI, он может ошибаться. Не пишите в чат конфиденциальные данные.', 'Напишите сообщение…', 'Отправить', 'Закрыть', 'Язык', 'Новый сайт', 'Хочу новый сайт. С чего начать?', 'Улучшить мой сайт', 'Хочу улучшить свой нынешний сайт.', 'Портфолио', 'Покажите похожие проекты из вашего портфолио.', 'Примерная цена', 'Сколько примерно стоит сайт?', 'Связаться со студией', 'Ассистент печатает…', 'Не удалось соединиться. Напишите нам на {e}.', 'Связь со студией', 'Имя', 'E-mail', 'Телефон (необязательно)', 'Сообщение', 'Я согласен(на) на обработку данных из этой формы и этого разговора, чтобы студия могла мне ответить.', 'Политика конфиденциальности', 'Отправить запрос', 'Спасибо, запрос отправлен. Один из нас свяжется с вами.', 'Укажите имя, корректный e-mail и согласие.', 'Новый разговор', 'Отмена', 'Не более {n} символов.', 'Вы', 'Ассистент']
    };
    var KEYS = ['open', 'intro', 'note', 'ph', 'send', 'close', 'lang', 'qNew', 'mNew', 'qImp', 'mImp', 'qRef', 'mRef', 'qPrice', 'mPrice', 'qContact', 'typing', 'err', 'fTitle', 'name', 'email', 'phone', 'msg', 'consent', 'privacy', 'submit', 'sent', 'fErr', 'newChat', 'cancel', 'limit', 'you', 'ai'];
    var MAX = C.maxChars || 1000;
    /* hlas zdarma přes prohlížeč: mic, micStop, listening, mute, unmute, denied, vnote, novoice */
    var VK = ['mic', 'micStop', 'listening', 'mute', 'unmute', 'denied', 'vnote', 'novoice'];
    var VT = {
      en: ['Speak', 'Stop listening', 'Listening…', 'Mute spoken replies', 'Read replies aloud', 'The microphone is not allowed or not working. You can type instead.', 'Voice input is recognised by your browser (e.g. Google, Apple or Microsoft); only the text reaches us. We do not store audio.', 'Your browser has no voice for this language, so the reply is text only.'],
      cs: ['Mluvit', 'Přestat poslouchat', 'Poslouchám…', 'Ztlumit předčítání', 'Předčítat odpovědi', 'Mikrofon není povolený nebo nefunguje. Můžete psát.', 'Řeč rozpozná váš prohlížeč (např. Google, Apple nebo Microsoft), k nám přijde jen text. Zvuk neukládáme.', 'Prohlížeč nemá hlas pro tento jazyk, odpověď je jen textem.'],
      de: ['Sprechen', 'Zuhören beenden', 'Ich höre zu…', 'Vorlesen stummschalten', 'Antworten vorlesen', 'Das Mikrofon ist nicht erlaubt oder funktioniert nicht. Sie können schreiben.', 'Die Sprache erkennt Ihr Browser (z. B. Google, Apple oder Microsoft); zu uns gelangt nur der Text. Wir speichern keine Audioaufnahmen.', 'Ihr Browser hat keine Stimme für diese Sprache, die Antwort gibt es nur als Text.'],
      es: ['Hablar', 'Dejar de escuchar', 'Escuchando…', 'Silenciar la lectura', 'Leer respuestas en voz alta', 'El micrófono no está permitido o no funciona. Puedes escribir.', 'Tu navegador reconoce la voz (p. ej. Google, Apple o Microsoft); a nosotros solo llega el texto. No guardamos audio.', 'Tu navegador no tiene voz para este idioma; la respuesta es solo texto.'],
      fr: ['Parler', 'Arrêter l’écoute', 'Je vous écoute…', 'Couper la lecture', 'Lire les réponses à voix haute', 'Le micro n’est pas autorisé ou ne fonctionne pas. Vous pouvez écrire.', 'La voix est reconnue par votre navigateur (p. ex. Google, Apple ou Microsoft) ; seul le texte nous parvient. Nous ne conservons pas l’audio.', 'Votre navigateur n’a pas de voix pour cette langue ; la réponse est seulement écrite.'],
      pt: ['Falar', 'Parar de ouvir', 'A ouvir…', 'Silenciar a leitura', 'Ler respostas em voz alta', 'O microfone não está autorizado ou não funciona. Pode escrever.', 'A voz é reconhecida pelo seu navegador (p. ex. Google, Apple ou Microsoft); a nós só chega o texto. Não guardamos áudio.', 'O seu navegador não tem voz para este idioma; a resposta é só texto.'],
      it: ['Parla', 'Smetti di ascoltare', 'Ti ascolto…', 'Disattiva la lettura', 'Leggi le risposte ad alta voce', 'Il microfono non è consentito o non funziona. Puoi scrivere.', 'La voce è riconosciuta dal tuo browser (ad es. Google, Apple o Microsoft); a noi arriva solo il testo. Non conserviamo l’audio.', 'Il tuo browser non ha una voce per questa lingua: la risposta è solo testo.'],
      pl: ['Mów', 'Przestań słuchać', 'Słucham…', 'Wycisz czytanie', 'Czytaj odpowiedzi na głos', 'Mikrofon nie jest dozwolony lub nie działa. Możesz pisać.', 'Mowę rozpoznaje Twoja przeglądarka (np. Google, Apple lub Microsoft); do nas trafia tylko tekst. Nie zapisujemy nagrań.', 'Przeglądarka nie ma głosu dla tego języka, odpowiedź jest tylko tekstem.'],
      nl: ['Spreken', 'Stoppen met luisteren', 'Ik luister…', 'Voorlezen dempen', 'Antwoorden voorlezen', 'De microfoon is niet toegestaan of werkt niet. Je kunt typen.', 'Je browser herkent de spraak (bijv. Google, Apple of Microsoft); alleen de tekst komt bij ons. We bewaren geen audio.', 'Je browser heeft geen stem voor deze taal; het antwoord is alleen tekst.'],
      uk: ['Говорити', 'Припинити слухати', 'Слухаю…', 'Вимкнути озвучення', 'Озвучувати відповіді', 'Мікрофон не дозволено або він не працює. Можна писати.', 'Мовлення розпізнає ваш браузер (напр. Google, Apple чи Microsoft); до нас надходить лише текст. Аудіо ми не зберігаємо.', 'Браузер не має голосу для цієї мови, відповідь лише текстом.'],
      ar: ['تحدّث', 'إيقاف الاستماع', 'أستمع…', 'كتم القراءة الصوتية', 'قراءة الردود بصوت عالٍ', 'الميكروفون غير مسموح أو لا يعمل. يمكنك الكتابة.', 'يتعرف متصفحك على الكلام (مثل Google أو Apple أو Microsoft)، ولا يصلنا إلا النص. لا نحفظ التسجيلات الصوتية.', 'لا يتوفر في متصفحك صوت لهذه اللغة، لذا الرد نصي فقط.'],
      hi: ['बोलें', 'सुनना बंद करें', 'सुन रही हूँ…', 'पढ़कर सुनाना बंद करें', 'जवाब पढ़कर सुनाएँ', 'माइक्रोफ़ोन की अनुमति नहीं है या वह काम नहीं कर रहा। आप लिख सकते हैं।', 'आवाज़ को आपका ब्राउज़र पहचानता है (जैसे Google, Apple या Microsoft); हम तक केवल टेक्स्ट पहुँचता है। हम ऑडियो सेव नहीं करते।', 'आपके ब्राउज़र में इस भाषा की आवाज़ नहीं है, इसलिए जवाब केवल टेक्स्ट में है।'],
      zh: ['说话', '停止聆听', '正在聆听…', '关闭朗读', '朗读回复', '麦克风未获允许或无法使用。您可以打字。', '语音由您的浏览器识别（如 Google、Apple 或 Microsoft），我们只收到文字。我们不保存音频。', '您的浏览器没有该语言的语音，回复仅以文字显示。'],
      ja: ['話す', '聞き取りを停止', '聞いています…', '読み上げをミュート', '回答を読み上げる', 'マイクが許可されていないか、動作していません。入力もできます。', '音声はお使いのブラウザ（Google、Apple、Microsoft など）が認識し、当方にはテキストのみが届きます。音声は保存しません。', 'お使いのブラウザにはこの言語の音声がないため、回答はテキストのみです。'],
      ko: ['말하기', '듣기 중지', '듣고 있어요…', '읽어주기 끄기', '답변 읽어주기', '마이크가 허용되지 않았거나 작동하지 않습니다. 입력해 주세요.', '음성은 브라우저(예: Google, Apple, Microsoft)가 인식하며 저희에게는 텍스트만 전달됩니다. 음성은 저장하지 않습니다.', '브라우저에 이 언어의 음성이 없어 답변은 텍스트로만 표시됩니다.'],
      tr: ['Konuş', 'Dinlemeyi durdur', 'Dinliyorum…', 'Sesli okumayı kapat', 'Yanıtları sesli oku', 'Mikrofon izni yok veya çalışmıyor. Yazabilirsiniz.', 'Konuşmayı tarayıcınız tanır (ör. Google, Apple veya Microsoft); bize yalnızca metin ulaşır. Ses kaydı saklamayız.', 'Tarayıcınızda bu dil için ses yok, yanıt yalnızca metin olarak gösterilir.'],
      id: ['Bicara', 'Berhenti mendengarkan', 'Mendengarkan…', 'Matikan pembacaan', 'Bacakan jawaban', 'Mikrofon tidak diizinkan atau tidak berfungsi. Anda bisa mengetik.', 'Suara dikenali oleh peramban Anda (mis. Google, Apple, atau Microsoft); kami hanya menerima teks. Kami tidak menyimpan audio.', 'Peramban Anda tidak punya suara untuk bahasa ini, jadi jawaban hanya berupa teks.'],
      ru: ['Говорить', 'Перестать слушать', 'Слушаю…', 'Выключить озвучку', 'Озвучивать ответы', 'Микрофон не разрешён или не работает. Можно писать.', 'Речь распознаёт ваш браузер (например, Google, Apple или Microsoft), к нам приходит только текст. Аудио мы не сохраняем.', 'В браузере нет голоса для этого языка, ответ только текстом.']
    };
    var LOC = { en: 'en-US', cs: 'cs-CZ', de: 'de-DE', es: 'es-ES', fr: 'fr-FR', pt: 'pt-PT', it: 'it-IT', pl: 'pl-PL', nl: 'nl-NL', uk: 'uk-UA', ar: 'ar-SA', hi: 'hi-IN', zh: 'zh-CN', ja: 'ja-JP', ko: 'ko-KR', tr: 'tr-TR', id: 'id-ID', ru: 'ru-RU' };
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var SS = window.speechSynthesis && window.SpeechSynthesisUtterance ? window.speechSynthesis : null;
    var VOICE = C.voice !== false && typeof SR === 'function';      /* Firefox aj. bez rozpoznání řeči: jen text */

    function pick(l) { l = String(l || '').toLowerCase().slice(0, 2); return LANGS.indexOf(l) >= 0 ? l : null; }
    var store = { get: function (k) { try { return sessionStorage.getItem('tsai:' + k); } catch (e) { return null; } }, set: function (k, v) { try { v === null ? sessionStorage.removeItem('tsai:' + k) : sessionStorage.setItem('tsai:' + k, v); } catch (e) { } } };
    var lang = pick(store.get('lang')) || pick(C.lang) || pick(document.documentElement.lang) || 'en';
    /* TS_AI_CONFIG.texts = { cs: { intro: '…', role: '…' }, '*': {…} } přepíše vestavěné texty (persona projektu) */
    function own(k) { var x = C.texts || {}; return (x[lang] && x[lang][k]) || (x['*'] && x['*'][k]) || ''; }
    function vt(k) { var a = VT[lang] || VT.en, i = VK.indexOf(k); return own(k) || a[i] || VT.en[i]; }
    function t(k, v) { var a = T[lang] || T.en, s = own(k) || a[KEYS.indexOf(k)] || T.en[KEYS.indexOf(k)] || k; v = v || {}; return s.replace('{b}', C.business || '').replace('{e}', C.email || '').replace('{n}', v.n || MAX); }
    function el(tag, attrs, kids) {
      var e = document.createElement(tag);
      for (var k in attrs || {}) { if (k === 'text') e.textContent = attrs[k]; else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), attrs[k]); else e.setAttribute(k, attrs[k]); }
      (kids || []).forEach(function (c) { if (c) e.appendChild(c); });
      return e;
    }
    function privacy() { var p = C.privacyUrl; if (!p) return ''; if (typeof p === 'string') return p; return p[lang] || p['*'] || ''; }
    function rid() { var a = new Uint8Array(16); (window.crypto || {}).getRandomValues ? crypto.getRandomValues(a) : a.forEach(function (_, i) { a[i] = Math.random() * 256; }); return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
    function post(route, body) {
      var ctl = window.AbortController ? new AbortController() : null, tm = setTimeout(function () { if (ctl) ctl.abort(); }, 45000);
      return fetch(API + (API.indexOf('?') < 0 ? '?' : '&') + 'r=' + route, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined })
        .then(function (r) { clearTimeout(tm); return r.json().catch(function () { return { ok: false, error: 'json' }; }).then(function (j) { j.status = r.status; return j; }); },
          function (e) { clearTimeout(tm); throw e; });
    }

    /* ---------- DOM ---------- */
    var root = el('div', { class: 'tsai', 'data-open': 'false' });
    var launcher = el('button', { type: 'button', class: 'tsai-launcher', 'aria-expanded': 'false', 'aria-controls': 'tsai-panel' });
    var panel = el('section', { id: 'tsai-panel', class: 'tsai-panel', role: 'dialog', 'aria-modal': 'false', hidden: '' });
    var title = el('h2', { class: 'tsai-title', id: 'tsai-title' }), role = el('p', { class: 'tsai-role' });
    var NAME = C.name || '';
    /* maskot persony (TS_AI_CONFIG.mascot): létající robůtek v SVG, pohyb řídí jen CSS podle data-state na .tsai */
    var BOT = '<svg viewBox="0 0 64 64" width="64" height="64" focusable="false"><ellipse class="tsai-bot-shadow" cx="32" cy="61" rx="11" ry="2"/>' +
      '<g class="tsai-bot-fly"><path class="tsai-bot-jet" d="M28 50h8l-4 9z"/><rect class="tsai-bot-base" x="25" y="45" width="14" height="7" rx="3.5"/>' +
      '<g class="tsai-bot-sig"><path d="M5 25a10 10 0 0 0 0 14"/><path d="M59 25a10 10 0 0 1 0 14"/></g>' +
      '<path class="tsai-bot-ant" d="M32 15V9"/><circle class="tsai-bot-tip" cx="32" cy="7" r="3.2"/>' +
      '<rect class="tsai-bot-ear" x="8.5" y="25" width="5" height="12" rx="2.5"/><rect class="tsai-bot-ear" x="50.5" y="25" width="5" height="12" rx="2.5"/>' +
      '<rect class="tsai-bot-head" x="12" y="14" width="40" height="33" rx="15"/><rect class="tsai-bot-visor" x="17" y="20" width="30" height="20" rx="10"/>' +
      '<g class="tsai-bot-eyes"><rect x="22.5" y="24.5" width="6" height="8" rx="3"/><rect x="35.5" y="24.5" width="6" height="8" rx="3"/></g>' +
      '<g class="tsai-bot-x"><path d="M23 25l5 6M28 25l-5 6M36 25l5 6M41 25l-5 6"/></g>' +
      '<g class="tsai-bot-mouth"><rect x="27.5" y="34" width="2" height="2.4" rx="1"/><rect x="31" y="34" width="2" height="2.4" rx="1"/><rect x="34.5" y="34" width="2" height="2.4" rx="1"/></g></g></svg>';
    function face(cls) {                     /* maskot, jinak statický portrét (TS_AI_CONFIG.avatar); když se portrét nenačte, zmizí */
      if (C.mascot) { var b = el('span', { class: 'tsai-bot ' + cls, 'aria-hidden': 'true' }); b.innerHTML = BOT; return b; }
      if (!C.avatar) return el('span', { class: 'tsai-dot', 'aria-hidden': 'true' });
      var i = el('img', { class: cls, src: C.avatar, alt: '', width: '64', height: '64', decoding: 'async', 'aria-hidden': 'true' });
      i.addEventListener('error', function () { i.remove(); }); return i;
    }
    var langSel = el('select', { class: 'tsai-lang', id: 'tsai-lang' });
    var langLbl = el('label', { class: 'tsai-sr', for: 'tsai-lang' });
    var newBtn = el('button', { type: 'button', class: 'tsai-icon tsai-new' });
    var closeBtn = el('button', { type: 'button', class: 'tsai-icon tsai-close' });
    var log = el('div', { class: 'tsai-log', role: 'log', 'aria-live': 'polite', 'aria-relevant': 'additions', tabindex: '0' });
    var quick = el('div', { class: 'tsai-quick' });
    var status = el('p', { class: 'tsai-status', 'aria-live': 'polite' });
    var input = el('textarea', { class: 'tsai-input', id: 'tsai-input', rows: '1', maxlength: String(MAX) });
    var inputLbl = el('label', { class: 'tsai-sr', for: 'tsai-input' });
    var sendBtn = el('button', { type: 'submit', class: 'tsai-send' });
    var micBtn = el('button', { type: 'button', class: 'tsai-icon tsai-mic', 'aria-pressed': 'false' });
    var muteBtn = el('button', { type: 'button', class: 'tsai-icon tsai-mute', 'aria-pressed': 'false' });
    var composer = el('form', { class: 'tsai-composer', novalidate: '' }, VOICE ? [inputLbl, input, micBtn, sendBtn] : [inputLbl, input, sendBtn]);
    var SVG_MIC = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>';
    var SVG_SPK = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path class="tsai-wave" d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg>';
    panel.setAttribute('aria-labelledby', 'tsai-title');
    LANGS.forEach(function (l) { langSel.appendChild(el('option', { value: l, text: NAMES[l], lang: l })); });
    var headFace = el('span', { class: 'tsai-head-face' });
    panel.appendChild(el('header', { class: 'tsai-head' }, [headFace, el('div', { class: 'tsai-who' }, [title, role]), langLbl, langSel, muteBtn, newBtn, closeBtn]));
    panel.appendChild(log); panel.appendChild(quick); panel.appendChild(status); panel.appendChild(composer);
    root.appendChild(panel); root.appendChild(launcher);

    var conv = store.get('conv') || '', busy = false, history = [];
    try { history = JSON.parse(store.get('hist') || '[]') || []; } catch (e) { history = []; }

    function label() {
      root.setAttribute('lang', lang); panel.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
      launcher.innerHTML = ''; launcher.appendChild(face('tsai-face')); launcher.appendChild(el('span', { text: NAME || t('open') }));
      if (NAME) launcher.setAttribute('aria-label', NAME + ', ' + (own('role') || t('open'))); else launcher.removeAttribute('aria-label');
      title.textContent = NAME || t('open'); role.textContent = NAME ? (own('role') || t('open')) : ''; role.hidden = !NAME;
      headFace.innerHTML = ''; if (C.mascot || C.avatar) headFace.appendChild(face('tsai-face tsai-face-lg')); headFace.hidden = !(C.mascot || C.avatar); langLbl.textContent = t('lang'); langSel.value = lang;
      newBtn.setAttribute('aria-label', t('newChat')); newBtn.title = t('newChat'); newBtn.textContent = '↺';
      closeBtn.setAttribute('aria-label', t('close')); closeBtn.title = t('close'); closeBtn.textContent = '×';
      input.placeholder = t('ph'); inputLbl.textContent = t('ph'); sendBtn.textContent = t('send');
            micBtn.innerHTML = SVG_MIC; micBtn.setAttribute('aria-label', listening ? vt('micStop') : vt('mic')); micBtn.title = vt('mic'); syncMute();
      quick.innerHTML = '';
      [['qNew', 'mNew'], ['qImp', 'mImp'], ['qRef', 'mRef'], ['qPrice', 'mPrice'], ['qContact', null]].forEach(function (q) {
        quick.appendChild(el('button', { type: 'button', class: 'tsai-chip', text: t(q[0]), onclick: function () { q[1] ? send(t(q[1])) : showForm(''); } }));
      });
      render();
    }

    function linkify(text, links) {
      var frag = document.createDocumentFragment(), ok = {};
      (links || []).forEach(function (l) { ok[l.url] = l; });
      var lines = String(text).split(/\n/), list = null;
      lines.forEach(function (line, i) {
        var li = /^\s*[-•]\s+/.test(line), target;
        if (li) { if (!list) { list = el('ul'); frag.appendChild(list); } target = el('li'); list.appendChild(target); line = line.replace(/^\s*[-•]\s+/, ''); }
        else { list = null; target = frag; if (i > 0 && line !== '') target.appendChild(el('br')); }
        line = line.replace(/\*\*([^*]+)\*\*/g, '$1');
        var re = /(https?:\/\/[^\s<>()"']+|\/[a-z0-9][a-z0-9\/_\-.]*\.html(?:\?[a-z0-9=&_\-]+)?)/gi, last = 0, m;
        while ((m = re.exec(line))) {
          var u = m[1].replace(/[.,;:!?]+$/, '');
          target.appendChild(document.createTextNode(line.slice(last, m.index)));
          if (ok[u]) { var ext = /^https?:/i.test(u); target.appendChild(el('a', ext ? { href: u, target: '_blank', rel: 'noopener', text: ok[u].title || u } : { href: u, text: ok[u].title || u })); }
          else target.appendChild(document.createTextNode(u));
          last = m.index + u.length; re.lastIndex = last;
        }
        target.appendChild(document.createTextNode(line.slice(last)));
      });
      return frag;
    }
    function bubble(role, text, links) {
      var b = el('div', { class: 'tsai-msg tsai-' + role });
      b.appendChild(el('span', { class: 'tsai-sr', text: (role === 'user' ? t('you') : NAME || t('ai')) + ': ' }));
      b.appendChild(role === 'user' ? document.createTextNode(text) : linkify(text, links));
      return b;
    }
    function render() {
      log.innerHTML = '';
      var intro = el('div', { class: 'tsai-msg tsai-assistant' }); intro.appendChild(el('span', { class: 'tsai-sr', text: (NAME || t('ai')) + ': ' })); intro.appendChild(document.createTextNode(t('intro')));
      var note = el('p', { class: 'tsai-note', text: t('note') + ' ' + (VOICE ? vt('vnote') + ' ' : '') }), pu = privacy();
      if (pu) note.appendChild(el('a', { href: pu, target: '_blank', rel: 'noopener', text: t('privacy') }));
      log.appendChild(intro); log.appendChild(note);
      history.forEach(function (h) { log.appendChild(bubble(h.r, h.t, h.l)); });
      quick.hidden = history.length > 0;
      log.scrollTop = log.scrollHeight;
    }
    function remember(r, text, links) { history.push({ r: r, t: text, l: links || [] }); if (history.length > 40) history = history.slice(-40); store.set('hist', JSON.stringify(history)); }
    function add(role, text, links) { remember(role, text, links); quick.hidden = true; log.appendChild(bubble(role, text, links)); log.scrollTop = log.scrollHeight; }

    function send(text, mode) {
      text = String(text || '').trim(); if (!text || busy) return;
      if (text.length > MAX) { status.textContent = t('limit'); return; }
      busy = true; sendBtn.disabled = true; input.value = ''; grow(); add('user', text);
      log.setAttribute('aria-busy', 'true'); status.textContent = t('typing'); root.classList.add('tsai-busy'); stopSpeak(); state('processing');
      post('message', { conversation: conv || undefined, text: text, lang: lang, page: location.pathname, mode: mode === 'voice' ? 'voice' : undefined })
        .then(function (j) {
          if (j.conversation) { conv = j.conversation; store.set('conv', conv); }
          var r = j.reply;
          if (r && (r.text || (r.actions || []).length)) {
            if (r.text) { add('assistant', r.text, r.links); speak(r.text, r.links); }
            if ((r.actions || []).indexOf('contact') >= 0) showForm(r.summary || '');
            if ((r.actions || []).indexOf('offer') >= 0 && (r.actions || []).indexOf('contact') < 0) {      /* nabídka formuláře: tlačítko, otevře návštěvník */
              var sum = r.summary || '', ob = el('button', { type: 'button', class: 'tsai-chip tsai-offer', text: t('qContact'), onclick: function () { ob.remove(); showForm(sum); } });
              log.appendChild(ob); log.scrollTop = log.scrollHeight;
            }
          } else { add('assistant', t('err')); showForm(''); }
        })
        .catch(function () { add('assistant', t('err')); showForm(''); })
        .then(function () { busy = false; sendBtn.disabled = false; log.removeAttribute('aria-busy'); if (status.textContent === t('typing')) status.textContent = ''; root.classList.remove('tsai-busy'); if (root.getAttribute('data-state') === 'processing' && !queued) state('idle'); if (!form) input.focus(); });
    }

    /* ---------- hlas: rozpoznání a předčítání řeči v prohlížeči (zdarma, zvuk nejde přes náš server) ----------
       Stavy pro avatara: událost 'tsai:state' na .tsai (idle | processing | listening | speaking | error), hranice slov 'tsai:boundary'. */
    var rec = null, listening = false, speakOn = store.get('speak') === '1', queued = false;    /* queued: předčítání čeká na začátek zvuku */
    function state(s, d) { root.setAttribute('data-state', s); try { root.dispatchEvent(new CustomEvent('tsai:state', { bubbles: true, detail: Object.assign({ state: s }, d || {}) })); } catch (e) { } }
    function syncMute() {
      muteBtn.hidden = !(VOICE && SS); muteBtn.innerHTML = SVG_SPK; muteBtn.classList.toggle('tsai-off', !speakOn);
      muteBtn.setAttribute('aria-pressed', speakOn ? 'true' : 'false'); var l = speakOn ? vt('mute') : vt('unmute'); muteBtn.setAttribute('aria-label', l); muteBtn.title = l;
    }
    function stopListen() { if (rec && listening) { try { rec.abort(); } catch (e) { } } }
    function listen() {
      if (!VOICE || busy) return;
      if (listening) { try { rec.stop(); } catch (e) { } return; }
      stopSpeak();
      if (SS) { try { SS.speak(new SpeechSynthesisUtterance('')); } catch (e) { } }      /* iOS: odemknout předčítání během kliknutí */
      var fin = '';
      rec = new SR(); rec.lang = LOC[lang] || 'en-US'; rec.interimResults = true; rec.continuous = false; rec.maxAlternatives = 1;
      rec.onstart = function () { listening = true; micBtn.setAttribute('aria-pressed', 'true'); micBtn.setAttribute('aria-label', vt('micStop')); root.classList.add('tsai-listening'); status.textContent = vt('listening'); state('listening'); };
      rec.onresult = function (e) { var all = ''; fin = ''; for (var i = 0; i < e.results.length; i++) { all += e.results[i][0].transcript; if (e.results[i].isFinal) fin += e.results[i][0].transcript; } input.value = all; grow(); };
      rec.onerror = function (e) { if (/not-allowed|service-not-allowed|audio-capture/.test(e.error || '')) status.textContent = vt('denied'); state('error', { error: e.error }); };
      rec.onend = function () {
        listening = false; root.classList.remove('tsai-listening'); micBtn.setAttribute('aria-pressed', 'false'); micBtn.setAttribute('aria-label', vt('mic'));
        if (status.textContent === vt('listening')) status.textContent = '';
        var txt = (fin || input.value).trim();
        if (txt) { if (SS && !speakOn) { speakOn = true; store.set('speak', '1'); syncMute(); } send(txt, 'voice'); }
        else if (root.getAttribute('data-state') === 'listening') state('idle');
      };
      try { rec.start(); } catch (e) { status.textContent = vt('denied'); state('error', { error: 'start' }); }
    }
    /* Nejpřirozenější dostupný hlas: neurální/online/prémiové (Edge „Natural“, Apple Enhanced/Premium, Google) před základními
       lokálními; ženský hlas (persona) před mužským. TS_AI_CONFIG.voiceName = přesný název hlasu, pokud ho zařízení má. */
    var FEM = /zuzana|vlasta|samantha|ava|allison|susan|karen|moira|tessa|serena|kate|jenny|aria|emma|michelle|sonia|libby|katja|amala|elke|anna|petra|helena|marlene|vicki|monica|paulina|elvira|lucia|ximena|amélie|amelie|audrey|aurelie|denise|eloise|alice|elsa|isabella|federica|ellen|fenna|colette|zosia|ewa|agnieszka|zofia|joana|raquel|fernanda|francisca|luciana|milena|katya|svetlana|dariya|lesya|polina|yelda|emel|damayanti|gadis|kyoko|nanami|mayu|yuna|sunhi|ting-ting|tingting|mei-jia|xiaoxiao|xiaoyi|lekha|swara|kalpana|laila|zariyah|salma|mariam|female|žensk/i;
    var MALE = /antonin|antonín|jakub|daniel|alex|fred|thomas|tom|aaron|arthur|guy|ryan|conrad|klaus|markus|jorge|diego|alvaro|henri|remy|luca|cosimo|xander|maarten|marek|duarte|yuri|maxim|ostap|cem|ardi|otoya|keita|injoon|yunxi|rishi|hamed|male|mužsk/i;
    function voiceScore(v) {
      var n = (v.name || '') + ' ' + (v.voiceURI || ''), sc = 0;
      if (/natural|neural|premium|enhanced|vylepš|wavenet|studio/i.test(n)) sc += 6;
      if (/online/i.test(n) || /google/i.test(n)) sc += 3;
      if (/compact|kompakt|espeak|robot/i.test(n)) sc -= 4;
      if (FEM.test(n)) sc += 2; else if (MALE.test(n)) sc -= 2;
      if (v.default) sc += 0.5;
      return sc;
    }
    function pickVoice() {
      if (!SS) return null;
      var vs = SS.getVoices() || [], l = (LOC[lang] || 'en-US').toLowerCase();
      if (C.voiceName) { var w = vs.filter(function (v) { return v.name === C.voiceName; })[0]; if (w) return w; }
      var m = vs.filter(function (v) { return (v.lang || '').replace('_', '-').toLowerCase() === l; });
      if (!m.length) m = vs.filter(function (v) { return (v.lang || '').toLowerCase().indexOf(lang) === 0; });
      return m.slice().sort(function (a, b) { return voiceScore(b) - voiceScore(a); })[0] || null;
    }
    /* úprava textu pro čtení: zkratky a značky, které hlasy čtou špatně */
    var AMP = { cs: ' a ', de: ' und ', es: ' y ', fr: ' et ', pt: ' e ', it: ' e ', pl: ' i ', nl: ' en ', uk: ' і ', ru: ' и ', tr: ' ve ', id: ' dan ' };
    function forSpeech(t) {
      return t.replace(/T\s*&\s*S/g, 'T' + (AMP[lang] || ' and ') + 'S').replace(/&/g, AMP[lang] || ' and ')
        .replace(/[()\[\]„“"»«]/g, ' ').replace(/\s+[–—-]\s+/g, ', ')
        .replace(/\s+/g, ' ').trim();
    }
    var beatT = 0;                             /* maskot: krátké „vyslovení“ na každou hranici slova */
    function beat() { if (!C.mascot) return; root.classList.add('tsai-beat'); clearTimeout(beatT); beatT = setTimeout(function () { root.classList.remove('tsai-beat'); }, 170); }
    function stopSpeak() { var was = queued; queued = false; if (SS && (SS.speaking || SS.pending || was)) { SS.cancel(); if (/speaking|processing/.test(root.getAttribute('data-state') || '') && was) state('idle'); } }
    function speak(text, links) {
      if (!VOICE || !SS || !speakOn) return;
      var v = pickVoice(); if (!v) { status.textContent = vt('novoice'); return; }
      var map = {}; (links || []).forEach(function (l) { map[l.url] = l.title || ''; });
      var plain = String(text).replace(/https?:\/\/\S+|\/[a-z0-9][\w\/.\-]*\.html\S*/gi, function (u) { return map[u.replace(/[.,;:!?)]+$/, '')] || ''; })
        .replace(/[*#•_]/g, '').replace(/\s+/g, ' ').trim();
      plain = forSpeech(plain);
      var sent = plain.match(/[^.!?。！？]+[.!?。！？]*/g) || [plain], parts = [], cur = '';
      sent.forEach(function (x) { if ((cur + x).length > 220 && cur) { parts.push(cur); cur = x; } else cur += x; });   /* úseky do ~220 znaků: plynulejší intonace, Chrome dlouhé promluvy neusekne */
      if (cur.trim()) parts.push(cur);
      SS.cancel(); queued = true;
      parts.forEach(function (p, i) {
        var u = new SpeechSynthesisUtterance(p.trim()); u.voice = v; u.lang = v.lang; u.rate = C.voiceRate || 1; u.pitch = 1;
        if (i === 0) u.onstart = function () { queued = false; state('speaking', { voice: v.name }); };     /* mluvení až se skutečným zvukem */
        u.onboundary = function (ev) { beat(); try { root.dispatchEvent(new CustomEvent('tsai:boundary', { bubbles: true, detail: { name: ev.name, charIndex: ev.charIndex, text: p } })); } catch (e) { } };
        if (i === parts.length - 1) { u.onend = function () { queued = false; if (/speaking|processing/.test(root.getAttribute('data-state') || '')) state('idle'); }; u.onerror = u.onend; }
        SS.speak(u);
      });
    }

    /* ---------- kontaktní formulář (osobní údaje jdou jen do brány, ne k AI) ---------- */
    var form = null;
    function field(id, lab, type, req, extra) {
      var i = el(type === 'textarea' ? 'textarea' : 'input', Object.assign({ id: 'tsai-f-' + id, name: id, class: 'tsai-field' }, type === 'textarea' ? { rows: '3', maxlength: '3000' } : { type: type, maxlength: '160' }, req ? { required: '', 'aria-required': 'true' } : {}, extra || {}));
      return [el('label', { for: 'tsai-f-' + id, text: lab }), i];
    }
    function showForm(summary) {
      if (form) { form.querySelector('[name=message]').value = form.querySelector('[name=message]').value || summary; form.querySelector('input').focus(); return; }
      var opened = Date.now(), sid = rid() + rid().slice(0, 8), err = el('p', { class: 'tsai-err', id: 'tsai-ferr', role: 'alert' });
      var hp = el('input', { type: 'text', name: 'hp', tabindex: '-1', autocomplete: 'off', class: 'tsai-hp', 'aria-hidden': 'true' });
      var cons = el('input', { type: 'checkbox', id: 'tsai-f-consent', name: 'consent', required: '', 'aria-required': 'true' });
      var consText = el('span', { text: t('consent') + ' ' }); var pu = privacy();
      if (pu) consText.appendChild(el('a', { href: pu, target: '_blank', rel: 'noopener', text: t('privacy') }));
      var f = [].concat(field('name', t('name'), 'text', true, { autocomplete: 'name' }), field('email', t('email'), 'email', true, { autocomplete: 'email', inputmode: 'email' }),
        field('phone', t('phone'), 'tel', false, { autocomplete: 'tel' }), field('message', t('msg'), 'textarea', false));
      var submit = el('button', { type: 'submit', class: 'tsai-send', text: t('submit') });
      var cancel = el('button', { type: 'button', class: 'tsai-chip', text: t('cancel'), onclick: function () { form.remove(); form = null; input.focus(); } });
      form = el('form', { class: 'tsai-form', novalidate: '', 'aria-labelledby': 'tsai-ftitle', 'aria-describedby': 'tsai-ferr' },
        [el('h3', { id: 'tsai-ftitle', text: t('fTitle') })].concat(f, [hp, el('label', { class: 'tsai-consent', for: 'tsai-f-consent' }, [cons, consText]), err, el('div', { class: 'tsai-actions' }, [submit, cancel])]));
      form.querySelector('[name=message]').value = summary || '';
      form.addEventListener('submit', function (e) {
        e.preventDefault(); if (submit.disabled) return;
        var v = function (n) { return form.querySelector('[name=' + n + ']').value.trim(); };
        var okMail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'));
        form.querySelector('[name=name]').setAttribute('aria-invalid', v('name') ? 'false' : 'true');
        form.querySelector('[name=email]').setAttribute('aria-invalid', okMail ? 'false' : 'true');
        if (!v('name') || !okMail || !cons.checked) { err.textContent = t('fErr'); (v('name') ? (okMail ? cons : form.querySelector('[name=email]')) : form.querySelector('[name=name]')).focus(); return; }
        submit.disabled = true; err.textContent = '';
        post('lead', { conversation: conv || undefined, submission_id: sid, name: v('name'), email: v('email'), phone: v('phone'), message: v('message'), consent: true, consentText: t('consent'), lang: lang, hp: hp.value, openedMs: Date.now() - opened })
          .then(function (j) {
            if (j.ok) { form.remove(); form = null; add('assistant', t('sent')); input.focus(); }
            else { submit.disabled = false; err.textContent = j.error === 'fields' || j.error === 'consent' ? t('fErr') : t('err'); }
          })
          .catch(function () { submit.disabled = false; err.textContent = t('err'); });
      });
      log.appendChild(form); form.querySelector('input').focus(); form.scrollIntoView({ block: 'nearest' });
    }

    /* ---------- otevření / zavření ---------- */
    function open() {
      panel.hidden = false; root.setAttribute('data-open', 'true'); launcher.setAttribute('aria-expanded', 'true'); store.set('open', '1');
      render(); setTimeout(function () { input.focus(); }, 30);
    }
    function close() { stopListen(); stopSpeak(); if (form) { form.remove(); form = null; } panel.hidden = true; root.setAttribute('data-open', 'false'); launcher.setAttribute('aria-expanded', 'false'); store.set('open', null); launcher.focus(); }
    function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; }
    launcher.addEventListener('click', function () { panel.hidden ? open() : close(); });
    closeBtn.addEventListener('click', close);
    newBtn.addEventListener('click', function () { stopSpeak(); conv = ''; history = []; store.set('conv', null); store.set('hist', null); if (form) { form.remove(); form = null; } render(); input.focus(); });
    langSel.addEventListener('change', function () { lang = pick(langSel.value) || 'en'; store.set('lang', lang); label(); langSel.focus(); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); if (listening) stopListen(); else if (SS && (SS.speaking || SS.pending)) stopSpeak(); else close(); } });
    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); } });
    composer.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });

    /* Tlačítko se ukáže, až brána potvrdí, že asistent běží (vypnutý v konfiguraci, vyčerpaný rozpočet nebo výpadek = žádné tlačítko).
       TS_AI_CONFIG.checkStatus = false: ukázat hned bez dotazu. */
    function mount() {
      root.hidden = true; document.body.appendChild(root); label();
      var show = function () { root.hidden = false; if (store.get('open') === '1' && window.innerWidth > 640) open(); };
      if (C.checkStatus === false) return show();
      fetch(API + (API.indexOf('?') < 0 ? '?' : '&') + 'r=status', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; }).then(function (j) { if (j && j.enabled) show(); }, function () { });
    }
    if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount);
    micBtn.addEventListener('click', listen);
    muteBtn.addEventListener('click', function () { speakOn = !speakOn; store.set('speak', speakOn ? '1' : null); if (!speakOn) stopSpeak(); syncMute(); });
    if (SS && SS.getVoices) { try { SS.getVoices(); } catch (e) { } }
    window.TSAiWidget = { open: open, close: close, send: send, listen: listen, speak: speak };
  } catch (e) { if (window.console) console.warn('ts-ai-widget:', e); }
})();
