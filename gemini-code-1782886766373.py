import logging
import random
import sqlite3
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
    MessageHandler,
    filters,
)

# Loglama ayarları
logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s", level=logging.INFO
)

TOKEN = "8795747023:AAEgRO3G_OflFrKsZdUoHSScdQWtBl7nE2o"
YONETICI_IDLERI = [-1003975458977]
DISCORD_LINK = "https://exovanguard.com/discord"

# Veritabanı Kurulumu
def db_kur():
    conn = sqlite3.connect("bilet_sistemi.db")
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS biletler (
            bilet_id INTEGER PRIMARY KEY,
            user_id INTEGER,
            user_name TEXT,
            mesaj TEXT,
            durum TEXT,
            son_grup_mesaj_id INTEGER
        )
    """)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS kullanici_durum (
            user_id INTEGER PRIMARY KEY,
            durum TEXT,
            aktif_bilet_id INTEGER
        )
    """)
    conn.commit()
    conn.close()

db_kur()

# Yardımcı DB Fonksiyonları
def db_sorgu(query, params=(), fetchall=False, commit=False):
    conn = sqlite3.connect("bilet_sistemi.db")
    cursor = conn.cursor()
    cursor.execute(query, params)
    res = cursor.fetchall() if fetchall else cursor.fetchone()
    if commit:
        conn.commit()
    conn.close()
    return res

async def baslat(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """/start komutu ile ana menüyü açar."""
    user = update.effective_user

    k_durum = db_sorgu("SELECT durum FROM kullanici_durum WHERE user_id = ?", (user.id,))
    if k_durum and k_durum[0] in ["mesaj_bekliyor", "yanit_bekliyor"]:
        await update.message.reply_text("⚠️ Lütfen önce açık olan bilet işlemine mesajınızı yazın veya iptal edin.")
        return

    klavye = [
        [InlineKeyboardButton("📦 Sipariş verdim, ne zaman onaylanır?", callback_data="siparis_onay")],
        [InlineKeyboardButton("🎫 Biletlerim", callback_data="biletlerim")],
        [InlineKeyboardButton("📞 İletişim & Destek", callback_data="iletisim")],
        [InlineKeyboardButton("🎮 Discord Sunucumuza Katıl", url=DISCORD_LINK)],
    ]

    await update.message.reply_text(
        text=f"Merhaba {user.first_name}! 👋\nDestek sistemimize hoş geldiniz. Lütfen yapmak istediğiniz işlemi seçin:",
        reply_markup=InlineKeyboardMarkup(klavye)
    )

async def buton_tıklama(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Buton tıklamalarını yönetir."""
    query = update.callback_query
    user = query.from_user
    await query.answer()

    if query.data == "siparis_onay":
        metin = (
            "📦 **Sipariş Onay Süreçleri Hakkında**\n\n"
            "Verdiğiniz siparişler sistemimize düştükten sonra ekibimiz tarafından sırayla kontrol edilmektedir.\n\n"
            "• Siparişler genellikle **15 ile 60 dakika** içerisinde onaylanır.\n\n"
            "Eğer siparişinizin üzerinden uzun bir süre geçtiyse, aşağıdaki butona tıklayarak hemen destek bileti oluşturabilirsiniz."
        )
        klavye = [
            [InlineKeyboardButton("🎫 Bilet Oluştur", callback_data="bilet_olustur")],
            [InlineKeyboardButton("🎮 Discord", url=DISCORD_LINK)],
            [InlineKeyboardButton("⬅️ Ana Menü", callback_data="ana_menu")]
        ]
        await query.edit_message_text(text=metin, reply_markup=InlineKeyboardMarkup(klavye), parse_mode="Markdown")

    elif query.data == "bilet_olustur":
        bilet_id = random.randint(100000, 999999)
        db_sorgu("INSERT OR REPLACE INTO kullanici_durum VALUES (?, ?, ?)", (user.id, "mesaj_bekliyor", bilet_id), commit=True)

        metin = (
            f"🎫 **Yeni Destek Bileti**\n\n"
            f"**Bilet Numarası:** `#{bilet_id}`\n\n"
            f"✏️ Lütfen sorunuzu tek bir mesaj halinde buraya yazıp gönderin."
        )
        klavye = [[InlineKeyboardButton("❌ İptal Et", callback_data="bilet_iptal")]]
        await query.edit_message_text(text=metin, reply_markup=InlineKeyboardMarkup(klavye), parse_mode="Markdown")

    elif query.data == "bilet_iptal":
        db_sorgu("DELETE FROM kullanici_durum WHERE user_id = ?", (user.id,), commit=True)
        await query.edit_message_text(
            text="❌ Bilet oluşturma işlemi iptal edildi.",
            reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Ana Menü", callback_data="ana_menu")]])
        )

    elif query.data == "biletlerim":
        biletler = db_sorgu(
            "SELECT bilet_id, durum FROM biletler WHERE user_id = ? ORDER BY bilet_id DESC LIMIT 5",
            (user.id,), fetchall=True
        )
        if not biletler:
            metin = "🎫 Henüz hiç destek biletiniz bulunmuyor."
        else:
            metin = "🗂️ **Son 5 Destek Biletiniz:**\n\n"
            for b_id, b_durum in biletler:
                durum_tr = "🟢 Açık" if b_durum == "acik" else "🟡 Cevaplandı" if b_durum == "cevaplandi" else "🔴 Kapalı"
                metin += f"• Bilet No: `#{b_id}` | Durum: {durum_tr}\n"

        klavye = [[InlineKeyboardButton("⬅️ Ana Menü", callback_data="ana_menu")]]
        await query.edit_message_text(text=metin, reply_markup=InlineKeyboardMarkup(klavye), parse_mode="Markdown")

    elif query.data.startswith("kullanici_yanitla_"):
        bilet_id = int(query.data.split("_")[2])
        db_sorgu("INSERT OR REPLACE INTO kullanici_durum VALUES (?, ?, ?)", (user.id, "yanit_bekliyor", bilet_id), commit=True)
        await query.message.reply_text("✏️ Lütfen destek ekibine iletmek istediğiniz yanıtınızı yazıp gönderin:")

    elif query.data.startswith("kullanici_kapat_"):
        bilet_id = int(query.data.split("_")[2])
        db_sorgu("UPDATE biletler SET durum = 'kapali' WHERE bilet_id = ?", (bilet_id,), commit=True)
        db_sorgu("DELETE FROM kullanici_durum WHERE user_id = ?", (user.id,), commit=True)
        await query.edit_message_text("🔒 Biletiniz sizin tarafınızdan kapatıldı. Teşekkür ederiz.")

        for admin_id in YONETICI_IDLERI:
            await context.bot.send_message(
                chat_id=admin_id,
                text=f"🔒 <code>#{bilet_id}</code> numaralı bilet kullanıcı tarafından kapatıldı.",
                parse_mode="HTML"
            )

    elif query.data == "iletisim":
        metin = (
            "📞 **İletişim ve Çalışma Saatleri**\n\n"
            "Destek ekibimiz her gün 09:00 - 23:00 arasında hizmet vermektedir.\n\n"
            "🎮 Daha hızlı destek için Discord sunucumuza katılabilirsiniz!"
        )
        klavye = [
            [InlineKeyboardButton("🎮 Discord Sunucumuza Katıl", url=DISCORD_LINK)],
            [InlineKeyboardButton("⬅️ Ana Menü", callback_data="ana_menu")]
        ]
        await query.edit_message_text(text=metin, reply_markup=InlineKeyboardMarkup(klavye), parse_mode="Markdown")

    elif query.data == "ana_menu":
        klavye = [
            [InlineKeyboardButton("📦 Sipariş verdim, ne zaman onaylanır?", callback_data="siparis_onay")],
            [InlineKeyboardButton("🎫 Biletlerim", callback_data="biletlerim")],
            [InlineKeyboardButton("📞 İletişim & Destek", callback_data="iletisim")],
            [InlineKeyboardButton("🎮 Discord Sunucumuza Katıl", url=DISCORD_LINK)],
        ]
        await query.edit_message_text(
            text="Lütfen yapmak istediğiniz işlemi seçin:",
            reply_markup=InlineKeyboardMarkup(klavye)
        )

async def bilet_mesajini_al(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Kullanıcının bilet oluşturma veya bilet yanıtlama mesajlarını yakalar."""
    user = update.effective_user
    mesaj_metni = update.message.text

    k_durum = db_sorgu("SELECT durum, aktif_bilet_id FROM kullanici_durum WHERE user_id = ?", (user.id,))
    if not k_durum:
        await update.message.reply_text("Menü dışı mesaj gönderdiniz. İşlem yapmak için lütfen /start komutunu kullanın.")
        return

    durum, bilet_id = k_durum[0], k_durum[1]
    kullanici_adi = f"@{user.username}" if user.username else "Kullanıcı adı yok"
    guvenli_mesaj = mesaj_metni.replace("<", "&lt;").replace(">", "&gt;")
    guvenli_isim = user.first_name.replace("<", "<").replace(">", ">")

    if durum == "mesaj_bekliyor":
        db_sorgu(
            "INSERT INTO biletler VALUES (?, ?, ?, ?, 'acik', NULL)",
            (bilet_id, user.id, kullanici_adi, guvenli_mesaj),
            commit=True
        )
        await update.message.reply_text(
            f"✅ **Biletiniz Alındı!**\n**Bilet No:** `#{bilet_id}`\nEkibimiz en kısa sürede yanıtlayacaktır.",
            parse_mode="Markdown"
        )

        yonetici_mesaji = (
            f"🔔 <b>YENİ DESTEK TALEBİ (TICKET)!</b>\n"
            f"--------------------------------------\n"
            f"🎫 <b>Bilet No:</b> <code>#{bilet_id}</code>\n"
            f"👤 <b>Gönderen:</b> {guvenli_isim} ({kullanici_adi})\n"
            f"🆔 <b>Kullanıcı ID:</b> <code>{user.id}</code>\n"
            f"📝 <b>Mesaj:</b> {guvenli_mesaj}"
        )

    elif durum == "yanit_bekliyor":
        db_sorgu("UPDATE biletler SET durum = 'acik' WHERE bilet_id = ?", (bilet_id,), commit=True)
        await update.message.reply_text("✅ Yanıtınız destek ekibine başarıyla iletildi.")

        yonetici_mesaji = (
            f"💬 <b>Kullanıcıdan Yeni Yanıt!</b>\n"
            f"--------------------------------------\n"
            f"🎫 <b>Bilet No:</b> <code>#{bilet_id}</code>\n"
            f"👤 <b>Gönderen:</b> {guvenli_isim}\n"
            f"🆔 <b>Kullanıcı ID:</b> <code>{user.id}</code>\n"
            f"📝 <b>Yeni Mesaj:</b> {guvenli_mesaj}"
        )
    else:
        return

    db_sorgu("DELETE FROM kullanici_durum WHERE user_id = ?", (user.id,), commit=True)

    for admin_id in YONETICI_IDLERI:
        try:
            sent_msg = await context.bot.send_message(chat_id=admin_id, text=yonetici_mesaji, parse_mode="HTML")
            db_sorgu(
                "UPDATE biletler SET son_grup_mesaj_id = ? WHERE bilet_id = ?",
                (sent_msg.message_id, bilet_id),
                commit=True
            )
        except Exception as e:
            logging.error(f"Gruba mesaj gönderilemedi: {e}")

async def admin_yanitini_ilet(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Admin gruptaki bir ticket mesajını yanıtladığında çalışır."""
    chat_id = update.effective_chat.id
    if chat_id not in YONETICI_IDLERI or not update.message.reply_to_message:
        return

    orijinal_mesaj = update.message.reply_to_message
    admin_cevabi = update.message.text

    if not orijinal_mesaj.text or "Kullanıcı ID:" not in orijinal_mesaj.text:
        await update.message.reply_text("❌ Bu mesaj bir ticket bildirimi değil, yanıtlanamaz.")
        return

    try:
        # Kullanıcı ID'sini parse et
        hedef_kullanici_id = None
        for satir in orijinal_mesaj.text.split("\n"):
            if "Kullanıcı ID:" in satir:
                temiz = ''.join(c for c in satir if c.isdigit())
                if temiz:
                    hedef_kullanici_id = int(temiz)
                    break

        if hedef_kullanici_id is None:
            await update.message.reply_text("❌ Kullanıcı ID'si bulunamadı.")
            return

        # Bilet ID'sini parse et
        bilet_id = None
        for satir in orijinal_mesaj.text.split("\n"):
            if "Bilet No:" in satir:
                temiz = ''.join(c for c in satir if c.isdigit())
                if temiz:
                    bilet_id = int(temiz)
                    break

        if bilet_id is None:
            await update.message.reply_text("❌ Bilet No bulunamadı.")
            return

        db_sorgu("UPDATE biletler SET durum = 'cevaplandi' WHERE bilet_id = ?", (bilet_id,), commit=True)

        kullaniciya_gidecek_mesaj = f"🔔 **Destek Ekibinden Yanıt Geldi (Bilet No: #{bilet_id}):**\n\n{admin_cevabi}"

        klavye = [
            [
                InlineKeyboardButton("✍️ Yanıtla", callback_data=f"kullanici_yanitla_{bilet_id}"),
                InlineKeyboardButton("🔒 Bileti Kapat", callback_data=f"kullanici_kapat_{bilet_id}")
            ],
            [InlineKeyboardButton("🎮 Discord", url=DISCORD_LINK)]
        ]

        await context.bot.send_message(
            chat_id=hedef_kullanici_id,
            text=kullaniciya_gidecek_mesaj,
            parse_mode="Markdown",
            reply_markup=InlineKeyboardMarkup(klavye)
        )
        await update.message.reply_text("✅ Yanıtınız kullanıcıya iletildi ve bilet durumu güncellendi.")

    except Exception as e:
        await update.message.reply_text(f"❌ Mesaj iletilemedi. Hata: {e}")
        logging.error(f"Admin yanıtı iletilemedi: {e}")

async def admin_biletleri_listele(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Adminler grupta /biletler yazdığında açık talepleri listeler."""
    if update.effective_chat.id not in YONETICI_IDLERI:
        return

    acik_biletler = db_sorgu(
        "SELECT bilet_id, user_name, durum FROM biletler WHERE durum != 'kapali' ORDER BY bilet_id ASC",
        fetchall=True
    )
    if not acik_biletler:
        await update.message.reply_text("🟢 Şu an sistemde aktif veya yanıt bekleyen açık bilet bulunmuyor.")
        return

    metin = "🗃️ <b>Sistemdeki Aktif Biletler Listesi:</b>\n\n"
    for b_id, u_name, b_durum in acik_biletler:
        emoji = "🔴 Soru Geldi" if b_durum == "acik" else "🟡 Cevaplandı"
        metin += f"• 🎫 <code>#{b_id}</code> | {u_name} | {emoji}\n"

    await update.message.reply_text(metin, parse_mode="HTML")

def main() -> None:
    app = Application.builder().token(TOKEN).build()

    app.add_handler(CommandHandler("start", baslat))
    app.add_handler(CommandHandler("biletler", admin_biletleri_listele))
    app.add_handler(CallbackQueryHandler(buton_tıklama))
    app.add_handler(MessageHandler(filters.Chat(YONETICI_IDLERI) & filters.REPLY & filters.TEXT, admin_yanitini_ilet))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, bilet_mesajini_al))

    print("Gelişmiş Ticket Botu veritabanıyla aktif edildi...")
    app.run_polling(allowed_updates=Update.ALL_TYPES)

if __name__ == "__main__":
    main()
