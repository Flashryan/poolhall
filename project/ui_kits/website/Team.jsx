/* Poolhall UI kit — Meet the Team */

function Team({ go }) {
  return (
    <main>
      {/* hero */}
      <section className="hero" style={{ background: "var(--navy-800)" }}>
        <div className="hero-media"><img src={window.PH_PHOTO.office} alt="" onError={e => e.target.style.display='none'} /></div>
        <div className="container">
          <div className="hero-inner" style={{ maxWidth: 720 }}>
            <p className="eyebrow on-dark">Meet the team</p>
            <h1 className="h-display">The people<br />behind Poolhall</h1>
            <p className="lead on-dark">A small, specialist team that believes recruitment should be honest, personable and built on real relationships, not targets.</p>
          </div>
        </div>
      </section>

      {/* our story */}
      <section className="section bg-white">
        <div className="container">
          <div className="split">
            <div>
              <p className="eyebrow">Our story</p>
              <h2 className="h2">Independent, and proud of it</h2>
              <p className="lead" style={{ marginTop: 14 }}>Poolhall was founded in 2021 on a simple idea: combine state-of-the-art technology with traditional principles and ethics.</p>
              <p className="ph-body" style={{ marginTop: 14 }}>Being independent means we answer to our candidates and clients, not to shareholders or quotas. That's why people come back to us, and why so many of our roles are exclusive.</p>
              <div style={{ display: "flex", gap: 28, marginTop: 26 }}>
                {window.PH_DATA.stats.slice(0, 3).map(s => (
                  <div key={s.label}>
                    <div style={{ fontFamily: "var(--font-display)", fontWeight: 600, fontSize: "1.9rem", color: "var(--brand)" }}>{s.value}</div>
                    <div className="ph-meta">{s.label}</div>
                  </div>
                ))}
              </div>
            </div>
            <div className="media-frame"><img src={window.PH_PHOTO.story} alt="The Poolhall team" /></div>
          </div>
        </div>
      </section>

      {/* team grid */}
      <section className="section bg-paper">
        <div className="container">
          <SectionHead eyebrow="Say hello" title="Meet the team" lead="The specialists you'll actually speak to." />
          <div className="team-grid">
            {window.PH_TEAM.map(m => (
              <article className="team-card" key={m.name}>
                <div className="team-photo"><img src={m.photo} alt={m.name} /></div>
                <h3 className="team-name">{m.name}</h3>
                <div className="team-role">{m.role}</div>
                <div className="team-tags">{m.tags.map(t => <span key={t}>{t}</span>)}</div>
                <p className="team-bio">{m.bio}</p>
                <div className="team-soc">
                  <a href="#" onClick={e => e.preventDefault()} aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>
                  </a>
                  <a href="#" onClick={e => e.preventDefault()} aria-label="Email"><Icon name="mail" /></a>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* join us CTA */}
      <section className="section tight bg-navy">
        <div className="container">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 24, flexWrap: "wrap" }}>
            <div style={{ flex: "1 1 320px" }}>
              <h2 className="h2 on-dark">Fancy joining the team?</h2>
              <p className="lead on-dark" style={{ marginTop: 10 }}>We're always keen to hear from great recruiters who share our values.</p>
            </div>
            <div style={{ display: "flex", gap: 12 }}>
              <Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => go("contact")}>Get in touch</Button>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}

window.Team = Team;
