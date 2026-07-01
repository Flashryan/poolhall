/* Poolhall UI kit v2 — About Us (company story, values, mission) */

function About({ go }) {
  const D = window.PH_DATA;
  const team = window.PH_TEAM || [];
  const values = [
    ["Honest, always", "Straight advice on salary, market and timelines, even when it isn't what you were hoping to hear. No spin, no pressure."],
    ["Ethical by default", "Doing recruitment the right way isn't a tagline, it's how we operate, from how we handle your data to how we represent you."],
    ["Genuinely personable", "Independent means a real conversation with someone who picks up the phone, listens properly and remembers who you are."],
    ["Specialist, not generalist", "Three sectors we know inside out, so the conversation starts in the right place and the introductions actually fit."],
  ];
  return (
    <main>
      <div className="pagehead photo">
        <img src={window.PH_IMG.heroTeam} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">About Poolhall</Label>
          <h1 style={{ maxWidth: "17ch" }}>Recruitment, done with care.</h1>
          <p className="lead">An independent West Midlands agency built in 2021 to prove that hiring can be honest, thoughtful and genuinely personable, across Construction, Manufacturing and Digital.</p>
        </div>
      </div>

      {/* ---- Our story ---- */}
      <section className="section">
        <div className="container">
          <div className="split">
            <div>
              <Label idx="01 /">Our story</Label>
              <h2 className="h2">Independent, by choice.</h2>
              <p className="lead" style={{ marginTop: 14 }}>Poolhall was founded in 2021 by Matthew Tonks to show recruitment can be done thoughtfully, without the pushy tactics and numbers games the industry is sometimes known for.</p>
              <p style={{ marginTop: 14, color: "var(--fg-2)" }}>Being independent means we answer to our clients and candidates, not a franchise target. Run from Grosvenor House on St Pauls Square in Birmingham, we've kept our roots in the West Midlands while helping people find roles the length of the country.</p>
              <p style={{ marginTop: 14, color: "var(--fg-2)" }}>With around 30 years of combined experience across our three sectors, we know the work, the markets and the people, and we treat every introduction like it matters. Because it does.</p>
              <div style={{ display: "flex", flexWrap: "wrap", marginTop: 28, border: "1px solid var(--border)", borderRadius: "var(--r)", overflow: "hidden" }}>
                {D.stats.map((s, i) => (
                  <div key={s.label} style={{ flex: "1 1 120px", padding: "20px", borderRight: i < D.stats.length - 1 ? "1px solid var(--border)" : "0" }}>
                    <div style={{ fontFamily: "var(--font-display)", fontWeight: 900, fontSize: "1.9rem", color: "var(--slate-900)" }}>{s.value}<span style={{ color: "var(--accent-ink)" }}>{s.suffix}</span></div>
                    <div style={{ fontFamily: "var(--font-mono)", fontSize: 11.5, color: "var(--mist-500)", marginTop: 6 }}>{s.label}</div>
                  </div>
                ))}
              </div>
            </div>
            <div className="media-collage">
              <div className="m tall"><img src={window.PH_IMG.aboutA} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
              <div className="m"><img src={window.PH_IMG.heroTeam} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
              <div className="m"><img src={window.PH_IMG.sectorMan} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
            </div>
          </div>
        </div>
      </section>

      {/* ---- What we stand for ---- */}
      <section className="section bg-alt">
        <div className="container">
          <SectionHead idx="02 /" label="What we stand for" title="Four things we won't compromise on" center
            lead="The same principles guide every call, every shortlist and every placement, whether you're hiring or looking for your next role." />
          <div className="points">
            {values.map((v, i) => (
              <div className="point" key={v[0]}>
                <div className="pi">// {String(i + 1).padStart(2, "0")}</div>
                <h3>{v[0]}</h3>
                <p>{v[1]}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---- Mission band ---- */}
      <PhotoBand img={window.PH_IMG.sectorMan} kicker="Our mission"
        title="State-of-the-art technology, traditional principles."
        text="We pair modern recruitment tools with old-fashioned values, honesty, hard work and real relationships, to match incredible roles with amazing people. That's the whole job, and we take it seriously.">
        <Button variant="primary" iconRight="arrow-right" onClick={() => go("contact")}>Get in touch</Button>
      </PhotoBand>

      {/* ---- The people behind it (teaser → team page) ---- */}
      <section className="section">
        <div className="container">
          <SectionHead idx="03 /" label="The people behind it" title="A small team you'll actually deal with"
            action={<Button variant="ghost" iconRight="arrow-right" onClick={() => go("team")}>Meet the team</Button>} />
          <div className="team-grid">
            {team.map(m => (
              <a className="tcard" key={m.name} href="#" onClick={e => { e.preventDefault(); go("team"); }} style={{ textDecoration: "none", color: "inherit", display: "block" }}>
                <div className="tph"><img src={m.photo} alt={m.name} onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="tb">
                  <div className="trole">{m.role}</div>
                  <h3>{m.name}</h3>
                  <div className="ttags">{m.tags.map(t => <span className="ttag" key={t}>{t}</span>)}</div>
                </div>
              </a>
            ))}
          </div>
        </div>
      </section>

      <CtaBands go={go} />
    </main>
  );
}

window.About = About;
