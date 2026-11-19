import base64,re
F='ds4k/project/fonts/'
def d(p,m): return f"data:{m};base64,"+base64.b64encode(open(p,'rb').read()).decode()
s=open('vratky_proto/src/app.html').read()
s=s.replace('{{FONT_SCREAMER}}',d(F+'FKScreamer-Upright.ttf','font/ttf')).replace('{{FONT_SLANTED}}',d(F+'FKScreamer-Slanted.ttf','font/ttf')).replace('{{FONT_REGULAR}}',d(F+'GoodSans-Regular.ttf','font/ttf')).replace('{{FONT_BOLD}}',d(F+'GoodSans-Bold.ttf','font/ttf'))
s=s.replace('{{LOGO_BLACK}}',d('vratky_proto/src/logo-black.png','image/png')).replace('{{LOGO_WHITE}}',d('vratky_proto/src/logo-white.png','image/png'))
assert not re.search(r'\{\{[A-Z_]+\}\}',s)
open('vratky_proto/vratky-4k-ukazka.html','w').write(s)
print(len(s))
