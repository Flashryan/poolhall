/* Poolhall UI kit v2 — Meet the Team / About */

function Team({ go }) {
  const D = window.PH_DATA;
  return (
    <main>
      <div className="pagehead photo">
        <img src={window.PH_IMG.aboutA} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">About Poolhall</Label>
          <h1 style={{ maxWidth: "18ch" }}>A local team that takes recruitment personally.</h1>
          <p className="lead">Founded in 2021 to do things differently, built on honesty, hard work and genuine relationships.</p>
        </div>
      </div>

      <section className="section">
        <div className="container">
          <div className="split">
            <div>
              <Label idx="01 /">Our story</Label>
              <h2 className="h2">Independent, by choice.</h2>
              <p className="lead" style={{ marginTop: 14 }}>Poolhall was founded to show recruitment can be done thoughtfully, without the pushy tactics and numbers games the industry is sometimes known for.</p>
              <p style={{ marginTop: 14, color: "var(--fg-2)" }}>Being independent means we answer to our clients and candidates, not a franchise target. With around 30 years of combined experience across Construction, Manufacturing and Digital, we know the work, the markets and the people, and we treat every introduction like it matters. Because it does.</p>
              <div style={{ display: "flex", gap: 0, marginTop: 26, border: "1px solid var(--border)", borderRadius: "var(--r)", overflow: "hidden" }}>
                {D.stats.slice(0, 3).map((s, i) => (
                  <div key={s.label} style={{ flex: 1, padding: "20px", borderRight: i < 2 ? "1px solid var(--border)" : "0" }}>
                    <div style={{ fontFamily: "var(--font-display)", fontWeight: 900, fontSize: "1.9rem", color: "var(--slate-900)" }}>{s.value}<span style={{ color: "var(--accent-ink)" }}>{s.suffix}</span></div>
                    <div style={{ fontFamily: "var(--font-mono)", fontSize: 11.5, color: "var(--mist-500)", marginTop: 6 }}>{s.label}</div>
                  </div>
                ))}
              </div>
            </div>
            <div className="media"><img src={window.PH_IMG.aboutB} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
          </div>
        </div>
      </section>

      <section className="section bg-alt">
        <div className="container">
          <SectionHead idx="02 /" label="Meet the team" title="The people you'll actually deal with" center />
          <div className="team-grid">
            {D.team.map(m => (
              <div className="tcard" key={m.name}>
                <div className="tph"><img src={m.photo} alt={m.name} onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="tb">
                  <div className="trole">{m.role}</div>
                  <h3>{m.name}</h3>
                  <div className="ttags">{m.tags.map(t => <span className="ttag" key={t}>{t}</span>)}</div>
                  <p className="tbio">{m.bio}</p>
                  <div className="tcon">
                    <a href="#" onClick={e => e.preventDefault()} aria-label="LinkedIn"><Icon name="linkedin" /></a>
                    <a href="#" onClick={e => e.preventDefault()} aria-label="Email"><Icon name="mail" /></a>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="cta-photo">
        <img src={window.PH_IMG.ctaPhoto} alt="" onError={e => e.target.style.display='none'} />
        <div className="inner">
          <Label dark idx="//">Work for us</Label>
          <h2>Fancy joining the team?</h2>
          <p>We're always keen to hear from good recruiters who do things the right way.</p>
          <div className="acts"><Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => go("contact")}>Get in touch</Button></div>
        </div>
      </section>
    </main>
  );
}

window.Team = Team;
