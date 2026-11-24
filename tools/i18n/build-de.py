#!/usr/bin/env python3
"""Write the German catalogs for Club Events (de_DE plus Swiss/Austrian
variants) and compile them with po2mo.php.

Until 1.4.0 these strings were forced to German on every site by a gettext
filter in the main plugin file. They now live in real catalogs, so German
applies only to German-language sites; translate.wordpress.org takes over
once the plugin is listed there.

Usage: python3 tools/i18n/build-de.py
"""
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LANG = ROOT / "club-events" / "languages"
LOCALES = ["de_DE", "de_DE_formal", "de_CH", "de_CH_informal", "de_AT"]

STRINGS = {
    "All": "Alle",
    "No upcoming events.": "Keine bevorstehenden Anlässe.",
    "No events found.": "Keine Anlässe gefunden.",
    "Add to Calendar": "Zum Kalender hinzufügen",
    "Add to Calendar (.ics)": "Zum Kalender (.ics)",
    "Subscribe": "Abonnieren",
    "Subscribed!": "Abonniert!",
    "Subscribing…": "Wird abonniert…",
    "Loading…": "Wird geladen…",
    "All day": "Ganztägig",
    "All-day event": "Ganztägiger Anlass",
    "Location": "Ort",
    "Details": "Details",
    "Upcoming": "Bevorstehend",
    "Past": "Vergangen",
    "Read more": "Weiterlesen",
    "Share": "Teilen",
    "Copy link": "Link kopieren",
    "Link copied!": "Link kopiert!",
    # Added in 1.6.1: every visitor-facing string (the 1.2.x live site
    # carried these as a hand-edited gettext map).
    "Stay Updated": "Bleib auf dem Laufenden",
    "Subscribe to receive email notifications when new events are posted.": "Abonniere und erhalte eine E-Mail, sobald ein neuer Anlass veröffentlicht wird.",
    "Your Name": "Dein Name",
    "Optional": "Optional",
    "Email Address": "E-Mail-Adresse",
    "you@example.com": "name@beispiel.ch",
    "Categories (optional)": "Kategorien (optional)",
    "You are now subscribed to event updates!": "Du erhältst ab jetzt Benachrichtigungen zu neuen Anlässen!",
    "You have been unsubscribed.": "Du wurdest abgemeldet.",
    "Subscriptions are disabled.": "Anmeldungen sind zurzeit deaktiviert.",
    "Please enter a valid email address.": "Bitte gib eine gültige E-Mail-Adresse ein.",
    "You are already subscribed.": "Du bist bereits angemeldet.",
    "Please check your email to confirm your subscription.": "Bitte prüfe deine E-Mails, um deine Anmeldung zu bestätigen.",
    "Invalid or expired confirmation link.": "Ungültiger oder abgelaufener Bestätigungslink.",
    "Hi %s,": "Hallo %s,",
    "Hi there,": "Hallo,",
    "Confirm your event subscription — %s": "Bestätige dein Anlass-Abonnement — %s",
    "Please click the link below to confirm your subscription to event updates:": "Bitte klicke auf den folgenden Link, um dein Abonnement für Anlass-Benachrichtigungen zu bestätigen:",
    "If you did not request this, please ignore this email.": "Falls du dich nicht angemeldet hast, kannst du diese E-Mail ignorieren.",
    "A new event has been posted: %s": "Ein neuer Anlass wurde veröffentlicht: %s",
    "Date: %s": "Datum: %s",
    "Location: %s": "Ort: %s",
    "More information:": "Weitere Informationen:",
    "Unsubscribe: %s": "Abmelden: %s",
    "New Event: %s": "Neuer Anlass: %s",
    "Event Details": "Anlass-Details",
    "More Information": "Weitere Informationen",
    "All Events": "Alle Anlässe",
    "All events": "Alle Anlässe",
    "More details": "Mehr Details",
    "No events": "Keine Anlässe",
    "%d events in %d": "%d Anlässe im Jahr %d",
    "Add to calendar": "Zum Kalender hinzufügen",
    "Search events…": "Anlässe durchsuchen…",
    "Search events": "Anlässe durchsuchen",
    "View": "Ansicht",
    "Tiles": "Kacheln",
    "List": "Liste",
    "Timeline": "Zeitstrahl",
    "Calendar": "Kalender",
    "Cards": "Karten",
    "Subscribe (ICS)": "Abonnieren (ICS)",
    "Subscribe — add all events to your calendar": "Abonnieren — alle Anlässe in deinen Kalender",
    "No events match your search.": "Keine Anlässe passen zu deiner Suche.",
    "Upcoming events": "Nächste Anlässe",
    "Events": "Anlässe",
    "Home": "Startseite",
    "Event not found.": "Anlass nicht gefunden.",
    "Categories": "Kategorien",
    "Category": "Kategorie",
    "Event Types": "Gruppen",
    "Event Type": "Gruppe",
    "Please %s to submit events.": "Bitte %s, um Anlässe einzureichen.",
    "Please %s to view your events.": "Bitte %s, um deine Anlässe zu sehen.",
    "log in": "melde dich an",
    "You do not have permission to submit events.": "Du darfst keine Anlässe einreichen.",
    "Event Title": "Titel des Anlasses",
    "Start": "Beginn",
    "End": "Ende",
    "Description": "Beschreibung",
    "— Select —": "— Auswählen —",
    "Color": "Farbe",
    "Submit Event": "Anlass einreichen",
    "You have not submitted any events yet.": "Du hast noch keine Anlässe eingereicht.",
    "Event": "Anlass",
    "Date": "Datum",
    "Status": "Status",
    "Published": "Veröffentlicht",
    "Pending": "Ausstehend",
    "Draft": "Entwurf",
    "Delete": "Löschen",
    "You must be logged in.": "Du musst angemeldet sein.",
    "Event submissions are not enabled.": "Das Einreichen von Anlässen ist nicht aktiviert.",
    "Please enter an event title.": "Bitte gib einen Titel ein.",
    "Please enter a start date.": "Bitte gib ein Startdatum ein.",
    "Event published successfully!": "Anlass erfolgreich veröffentlicht!",
    "Event submitted for review. An admin will approve it shortly.": "Anlass zur Prüfung eingereicht. Ein Admin gibt ihn in Kürze frei.",
    "Not authorized.": "Nicht berechtigt.",
    "You can only delete your own events.": "Du kannst nur eigene Anlässe löschen.",
    "Published events can only be deleted by an admin.": "Veröffentlichte Anlässe kann nur ein Admin löschen.",
    "Submitting…": "Wird gesendet…",
    "Something went wrong. Please try again.": "Etwas ist schiefgelaufen. Bitte versuche es erneut.",
    "Delete this event?": "Diesen Anlass löschen?",
    "My Events": "Meine Anlässe",
    # Post type and taxonomy labels (breadcrumbs, archive titles, admin).
    "Add Event": "Anlass hinzufügen",
    "Add New Event": "Neuen Anlass hinzufügen",
    "Edit Event": "Anlass bearbeiten",
    "New Event": "Neuer Anlass",
    "View Event": "Anlass ansehen",
    "Search Events": "Anlässe durchsuchen",
    "No events in trash.": "Keine Anlässe im Papierkorb.",
    "Tag": "Schlagwort",
    "Tags": "Schlagwörter",
    "All Categories": "Alle Kategorien",
    "All Types": "Alle Gruppen",
    "Add New Category": "Neue Kategorie",
    "Add New Type": "Neue Gruppe",
    "Edit Category": "Kategorie bearbeiten",
    "Edit Type": "Gruppe bearbeiten",
    "Search Categories": "Kategorien durchsuchen",
    "Search Types": "Gruppen durchsuchen",
    "No categories found.": "Keine Kategorien gefunden.",
    "No types found.": "Keine Gruppen gefunden.",
    "No tags found.": "Keine Schlagwörter gefunden.",
}


def esc(text):
    return text.replace("\\", "\\\\").replace('"', '\\"')


def main():
    LANG.mkdir(parents=True, exist_ok=True)
    for locale in LOCALES:
        lines = [
            'msgid ""',
            'msgstr ""',
            '"Project-Id-Version: WP Club Events Simple\\n"',
            f'"Language: {locale}\\n"',
            '"MIME-Version: 1.0\\n"',
            '"Content-Type: text/plain; charset=UTF-8\\n"',
            '"Content-Transfer-Encoding: 8bit\\n"',
            '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
            '"X-Domain: club-events\\n"',
            "",
        ]
        for msgid, msgstr in STRINGS.items():
            lines += [f'msgid "{esc(msgid)}"', f'msgstr "{esc(msgstr)}"', ""]
        po = LANG / f"club-events-{locale}.po"
        po.write_text("\n".join(lines), encoding="utf-8")
        subprocess.run(["php", str(ROOT / "tools/i18n/po2mo.php"), str(po)], check=True)


if __name__ == "__main__":
    main()
