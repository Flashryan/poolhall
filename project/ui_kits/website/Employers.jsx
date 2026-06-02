/* Poolhall UI kit — Employers page (distinct journey + enquiry form) */

function Employers({ go }) {
  const D = window.PH_DATA;
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  const submit = e => { e.preventDefault(); setBusy(true); setTimeout(() => { setBusy(false); setSent(true); }, 1200); };
  return (
    <main>
      {/* hero */}
      <section className="hero" style={{ background: "var(--navy-900)" }}>
        <div className="hero-media"><img src={window.PH_IMG.employersHero} alt="" onError={e => e.target.style.display='none'} /></div>
        <div className="container">
          <div className="hero-inner" style={{ maxWidth: 680 }}>
            <p className="eyebrow on-dark">For employers</p>
            <h1 className="h-display">Find the right<br />people, faster</h1>
            <p className="lead on-dark">An independent partner that represents your business like its own. Exclusive shortlists, honest advice and a personable service across Construction, Manufacturing and Marketing.</p>
            <div style={{ display: "flex", gap: 12, marginTop: 30 }}>
              <Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => { const el = document.getElementById("enquiry"); if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 80, behavior: "smooth" }); }}>Get in touch</Button>
              <Button variant="ghost-light" size="lg" icon="phone">0121 516 3000</Button>
            </div>
          </div>
        </div>
      </section>

      {/* services */}
      <section className="section bg-white">
        <div className="container">
          <SectionHead eyebrow="Our services" title="Three ways we help you hire" lead="Flexible support whether you want us to run the whole search or just lend a hand." />
          <div className="steps">
            <div className="step"><span className="num"><Icon name="briefcase" /></span><h4>Permanent placement</h4><p>Bespoke, end-to-end search for permanent roles. We manage sourcing, screening and offer.</p></div>
            <div className="step"><span className="num"><Icon name="users" /></span><h4>Temporary staffing</h4><p>Reliable workforce support when you need to flex up quickly without the overhead.</p></div>
            <div className="step"><span className="num"><Icon name="megaphone" /></span><h4>Job advertising</h4><p>Keep recruitment in-house with optional CV sifting and candidate interviewing bolt-ons.</p></div>
          </div>
        </div>
      </section>

      {/* why poolhall split */}
      <section className="section bg-paper">
        <div className="container">
          <div className="split">
            <div className="media-frame"><img src={window.PH_IMG.whyPoolhall} alt="" onError={e => e.target.parentElement.style.background='var(--navy-700)'} /></div>
            <div>
              <p className="eyebrow">Why Poolhall</p>
              <h2 className="h2">Quality and ethics, not quotas</h2>
              <p className="lead" style={{ marginTop: 14 }}>We're an independent agency, so you get a more personable service and a partner genuinely invested in the right outcome.</p>
              <ul className="check-list">
                <li><Icon name="check-circle" /> Almost 50 years' combined recruitment experience</li>
                <li><Icon name="check-circle" /> Sector specialists in Construction, Manufacturing & Marketing</li>
                <li><Icon name="check-circle" /> Exclusive roles and a transparent, honest process</li>
                <li><Icon name="check-circle" /> Rated 5.0 by the candidates and clients we work with</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* sectors strip */}
      <section className="section tight bg-navy">
        <div className="container">
          <SectionHead dark eyebrow="Sectors we recruit in" title="Specialists where it counts" />
          <div className="sector-grid">
            {D.sectors.map(s => (
              <div key={s.name} className="sector-tile" style={{ background: "rgba(255,255,255,.04)", borderColor: "rgba(255,255,255,.12)" }}>
                <span className="sector-ic" style={{ background: "var(--orange-500)" }}><Icon name={s.icon} /></span>
                <span><span className="st-name" style={{ color: "#fff" }}>{s.name}</span></span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* enquiry form */}
      <section className="section bg-white" id="enquiry">
        <div className="container">
          <div className="split" style={{ alignItems: "start" }}>
            <div>
              <p className="eyebrow">Looking to hire?</p>
              <h2 className="h2">Tell us who you need</h2>
              <p className="lead" style={{ marginTop: 14 }}>Send us a few details and we'll come back to you within one working day. No obligation, no hard sell.</p>
              <ul className="check-list" style={{ marginTop: 26 }}>
                <li><Icon name="phone" /> 0121 516 3000</li>
                <li><Icon name="mail" /> jobs@poolhallrecruitment.co.uk</li>
                <li><Icon name="map-pin" /> 11 St Pauls Square, Birmingham, B3 1RB</li>
              </ul>
            </div>
            <div className="form-card">
              {sent ? (
                <div style={{ textAlign: "center", padding: "20px 0" }}>
                  <div style={{ width: 56, height: 56, borderRadius: "50%", background: "var(--success-50)", color: "var(--success-600)", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 16px" }}><Icon name="check" style={{ width: 26, height: 26 }} /></div>
                  <h3 className="h3">Thanks, we'll be in touch</h3>
                  <p className="ph-body" style={{ marginTop: 8 }}>One of the team will reply within one working day.</p>
                </div>
              ) : (
                <form onSubmit={submit}>
                  <div className="form-row">
                    <div className="fieldset"><label>Your name <span className="req">*</span></label><input className="input" required placeholder="Jane Smith" /></div>
                    <div className="fieldset"><label>Company <span className="req">*</span></label><input className="input" required placeholder="Acme Ltd" /></div>
                  </div>
                  <div className="form-row">
                    <div className="fieldset"><label>Email <span className="req">*</span></label><input className="input" type="email" required placeholder="jane@acme.com" /></div>
                    <div className="fieldset"><label>Phone</label><input className="input" placeholder="07700 900000" /></div>
                  </div>
                  <div className="fieldset"><label>What are you hiring for?</label><textarea className="input" placeholder="Roles, sectors, timescales…"></textarea></div>
                  <Button variant="primary" size="lg" className="btn-block" iconRight="arrow-right" busy={busy} type="submit">{busy ? "Sending…" : "Send enquiry"}</Button>
                </form>
              )}
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}

window.Employers = Employers;
