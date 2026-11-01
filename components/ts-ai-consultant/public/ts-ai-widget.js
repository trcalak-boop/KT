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

    function pick(l) { l = String(l || '').toLowerCase().slice(0, 2); return LANGS.indexOf(l) >= 0 ? l : null; }
    var store = { get: function (k) { try { return sessionStorage.getItem('tsai:' + k); } catch (e) { return null; } }, set: function (k, v) { try { v === null ? sessionStorage.removeItem('tsai:' + k) : sessionStorage.setItem('tsai:' + k, v); } catch (e) { } } };
    var lang = pick(store.get('lang')) || pick(C.lang) || pick(document.documentElement.lang) || 'en';
    function t(k, v) { var a = T[lang] || T.en, s = a[KEYS.indexOf(k)] || T.en[KEYS.indexOf(k)] || k; v = v || {}; return s.replace('{b}', C.business || '').replace('{e}', C.email || '').replace('{n}', v.n || MAX); }
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
    var title = el('h2', { class: 'tsai-title', id: 'tsai-title' });
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
    var composer = el('form', { class: 'tsai-composer', novalidate: '' }, [inputLbl, input, sendBtn]);
    panel.setAttribute('aria-labelledby', 'tsai-title');
    LANGS.forEach(function (l) { langSel.appendChild(el('option', { value: l, text: NAMES[l], lang: l })); });
    panel.appendChild(el('header', { class: 'tsai-head' }, [title, langLbl, langSel, newBtn, closeBtn]));
    panel.appendChild(log); panel.appendChild(quick); panel.appendChild(status); panel.appendChild(composer);
    root.appendChild(panel); root.appendChild(launcher);

    var conv = store.get('conv') || '', busy = false, history = [];
    try { history = JSON.parse(store.get('hist') || '[]') || []; } catch (e) { history = []; }

    function label() {
      root.setAttribute('lang', lang); panel.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
      launcher.innerHTML = ''; launcher.appendChild(el('span', { class: 'tsai-dot', 'aria-hidden': 'true' })); launcher.appendChild(el('span', { text: t('open') }));
      title.textContent = t('open'); langLbl.textContent = t('lang'); langSel.value = lang;
      newBtn.setAttribute('aria-label', t('newChat')); newBtn.title = t('newChat'); newBtn.textContent = '↺';
      closeBtn.setAttribute('aria-label', t('close')); closeBtn.title = t('close'); closeBtn.textContent = '×';
      input.placeholder = t('ph'); inputLbl.textContent = t('ph'); sendBtn.textContent = t('send');
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
      b.appendChild(el('span', { class: 'tsai-sr', text: (role === 'user' ? t('you') : t('ai')) + ': ' }));
      b.appendChild(role === 'user' ? document.createTextNode(text) : linkify(text, links));
      return b;
    }
    function render() {
      log.innerHTML = '';
      var intro = el('div', { class: 'tsai-msg tsai-assistant' }); intro.appendChild(el('span', { class: 'tsai-sr', text: t('ai') + ': ' })); intro.appendChild(document.createTextNode(t('intro')));
      log.appendChild(intro); log.appendChild(el('p', { class: 'tsai-note', text: t('note') }));
      history.forEach(function (h) { log.appendChild(bubble(h.r, h.t, h.l)); });
      quick.hidden = history.length > 0;
      log.scrollTop = log.scrollHeight;
    }
    function remember(r, text, links) { history.push({ r: r, t: text, l: links || [] }); if (history.length > 40) history = history.slice(-40); store.set('hist', JSON.stringify(history)); }
    function add(role, text, links) { remember(role, text, links); quick.hidden = true; log.appendChild(bubble(role, text, links)); log.scrollTop = log.scrollHeight; }

    function send(text) {
      text = String(text || '').trim(); if (!text || busy) return;
      if (text.length > MAX) { status.textContent = t('limit'); return; }
      busy = true; sendBtn.disabled = true; input.value = ''; grow(); add('user', text);
      log.setAttribute('aria-busy', 'true'); status.textContent = t('typing'); root.classList.add('tsai-busy');
      post('message', { conversation: conv || undefined, text: text, lang: lang, page: location.pathname })
        .then(function (j) {
          if (j.conversation) { conv = j.conversation; store.set('conv', conv); }
          var r = j.reply;
          if (r && (r.text || (r.actions || []).length)) {
            if (r.text) add('assistant', r.text, r.links);
            if ((r.actions || []).indexOf('contact') >= 0) showForm(r.summary || '');
          } else { add('assistant', t('err')); showForm(''); }
        })
        .catch(function () { add('assistant', t('err')); showForm(''); })
        .then(function () { busy = false; sendBtn.disabled = false; log.removeAttribute('aria-busy'); status.textContent = ''; root.classList.remove('tsai-busy'); if (!form) input.focus(); });
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
    function close() { if (form) { form.remove(); form = null; } panel.hidden = true; root.setAttribute('data-open', 'false'); launcher.setAttribute('aria-expanded', 'false'); store.set('open', null); launcher.focus(); }
    function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; }
    launcher.addEventListener('click', function () { panel.hidden ? open() : close(); });
    closeBtn.addEventListener('click', close);
    newBtn.addEventListener('click', function () { conv = ''; history = []; store.set('conv', null); store.set('hist', null); if (form) { form.remove(); form = null; } render(); input.focus(); });
    langSel.addEventListener('change', function () { lang = pick(langSel.value) || 'en'; store.set('lang', lang); label(); langSel.focus(); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); close(); } });
    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); } });
    composer.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });

    function mount() { document.body.appendChild(root); label(); if (store.get('open') === '1' && window.innerWidth > 640) open(); }
    if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount);
    window.TSAiWidget = { open: open, close: close, send: send };
  } catch (e) { if (window.console) console.warn('ts-ai-widget:', e); }
})();
