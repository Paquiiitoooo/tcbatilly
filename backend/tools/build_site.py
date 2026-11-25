"""
Génère les pages HTML du site public à partir du gabarit commun.

    python backend/tools/build_site.py

- gabarit : backend/tools/site/layout.html (en-tête, pied de page)
- pages   : backend/tools/site/pages/*.html (un fichier par page)
- sortie  : les .html à la racine du site, plus sitemap.xml

Chaque page commence par quelques lignes « clé: valeur », puis une ligne « --- »,
puis son contenu. Clés :
    title, description  balises <title> et meta description
    crumb               nom dans le fil d'Ariane (défaut : title)
    nav                 entrée de menu active (club, offres, calendrier, actualites, contact)
    page                valeur de data-page sur <body>
    file                nom du fichier de sortie
    partners            « none » pour ne pas ajouter la bande des partenaires
    robots              valeur de la balise robots (défaut : index, follow)
    sitemap             « no » pour exclure la page du plan du site
    priority, freq      pour le plan du site
"""
import datetime
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
SITE = ROOT / "backend" / "tools" / "site"
BASE_URL = "https://tc-batilly.fr/"
NAV_KEYS = ["club", "offres", "calendrier", "actualites", "contact"]
DEFAULT_ROBOTS = "index, follow, max-image-preview:large"

CLUB = {
    "@type": "SportsClub",
    "@id": BASE_URL + "#club",
    "name": "Tennis Club de Batilly",
    "alternateName": "TC Batilly",
    "url": BASE_URL,
    "logo": BASE_URL + "assets/images/logo.jpg",
    "image": BASE_URL + "assets/images/og-image.png",
    "description": "Club de tennis à Batilly, en Meurthe-et-Moselle, depuis 1989 : adhésions, entraînements, tournois et stages.",
    "foundingDate": "1989",
    "sport": "Tennis",
    "areaServed": "Meurthe-et-Moselle",
    "address": {
        "@type": "PostalAddress",
        "addressLocality": "Batilly",
        "postalCode": "54980",
        "addressRegion": "Meurthe-et-Moselle",
        "addressCountry": "FR",
    },
    "sameAs": ["https://tenup.fft.fr/club/55540452"],
    "potentialAction": {
        "@type": "ReserveAction",
        "target": "https://tenup.fft.fr/club/55540452/reservations",
    },
}

WEBSITE = {
    "@type": "WebSite",
    "@id": BASE_URL + "#site",
    "url": BASE_URL,
    "name": "Tennis Club de Batilly",
    "inLanguage": "fr-FR",
    "publisher": {"@id": BASE_URL + "#club"},
}


def parse(path):
    head, _, body = path.read_text(encoding="utf-8").partition("\n---\n")
    meta = {}
    for line in head.splitlines():
        if ":" in line:
            key, value = line.split(":", 1)
            meta[key.strip()] = value.strip()
    return meta, body.strip("\n")


def jsonld(meta, out_name, url):
    graph = [CLUB]
    if out_name == "index.html":
        graph.append(WEBSITE)
    elif out_name != "404.html":
        crumb = meta.get("crumb") or meta["title"].split("|")[0].strip()
        graph.append({
            "@type": "BreadcrumbList",
            "itemListElement": [
                {"@type": "ListItem", "position": 1, "name": "Accueil", "item": BASE_URL},
                {"@type": "ListItem", "position": 2, "name": crumb, "item": url},
            ],
        })
    data = {"@context": "https://schema.org", "@graph": graph}
    return (
        '    <script type="application/ld+json" id="ld-club">\n'
        + json.dumps(data, ensure_ascii=False, indent=4)
        + "\n    </script>"
    )


def write_sitemap(entries):
    today = datetime.date.today().isoformat()
    lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for url, priority, freq in entries:
        lines += [
            "    <url>",
            f"        <loc>{url}</loc>",
            f"        <lastmod>{today}</lastmod>",
            f"        <changefreq>{freq}</changefreq>",
            f"        <priority>{priority}</priority>",
            "    </url>",
        ]
    lines.append("</urlset>")
    (ROOT / "sitemap.xml").write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")
    print("écrit : sitemap.xml (%d pages)" % len(entries))


def main():
    layout = (SITE / "layout.html").read_text(encoding="utf-8")
    partners = (SITE / "partners.html").read_text(encoding="utf-8").rstrip("\n")
    pages = sorted((SITE / "pages").glob("*.html"))
    if not pages:
        sys.exit("Aucune page dans " + str(SITE / "pages"))

    sitemap = []
    for path in pages:
        meta, content = parse(path)
        out_name = meta.get("file", path.name)
        if "{{partners}}" in content:
            content = content.replace("{{partners}}", partners)
        elif meta.get("partners") != "none":
            content = content + "\n\n" + partners
        url = BASE_URL if out_name == "index.html" else BASE_URL + out_name

        replacements = {
            "{{title}}": meta["title"],
            "{{description}}": meta["description"],
            "{{url}}": url,
            "{{page}}": meta.get("page", path.stem),
            "{{robots}}": meta.get("robots", DEFAULT_ROBOTS),
            "{{content}}": content,
            "{{jsonld}}": jsonld(meta, out_name, url),
        }
        for key in NAV_KEYS:
            active = meta.get("nav") == key
            replacements["{{nav_" + key + "}}"] = ' class="active" aria-current="page"' if active else ""

        html = layout
        for token, value in replacements.items():
            html = html.replace(token, value)
        (ROOT / out_name).write_text(html, encoding="utf-8", newline="\n")
        print("écrit :", out_name)

        indexable = meta.get("robots", DEFAULT_ROBOTS).startswith("index") and meta.get("sitemap") != "no"
        if indexable:
            sitemap.append((url, meta.get("priority", "0.7"), meta.get("freq", "monthly")))

    sitemap.sort(key=lambda entry: (entry[0] != BASE_URL, entry[0]))
    write_sitemap(sitemap)


if __name__ == "__main__":
    main()
