/* Poolhall UI kit v2 — Contact (three routes, §9.8) */

function Contact({ go }) {
  const [route, setRoute] = useState("employer");
  const [sent, setSent] = useState(false);
  const routes = [
    { id: "employer", label: "I want to hire", icon: "building-2" },
    { id: "candidate", label: "I'm looking for work", icon: "user-round" },
    { id: "general", label: "General enquiry", icon: "message-circle" },
  ];
  return (
    <main className="bg-alt">
      <div className="pagehead photo">
        <img src={window.PH_IMG.heroTeam} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">Contact</Label>
          <h1>Let's talk</h1>
          <p className="lead">Choose whichever way suits you best and we'll make sure the right person gets back to you quickly. There's always a real person here to help.</p>
        </div>
      </div>

      <div className="container" style={{ padding: "44px 32px 88px" }}>
        <div style={{ display: "grid", gridTemplateColumns: "340px 1fr", gap: 44, alignItems: "start" }} className="contact-cols">
          {/* left rail */}
          <div>
            {[
              { icon: "phone", t: "Call us", v: "0121 516 3000", s: "Mon–Fri, 8:30–5:30" },
              { icon: "mail", t: "Email", v: "hello@poolhallrecruitment.co.uk", s: "We reply within one working day" },
              { icon: "map-pin", t: "Visit", v: "Grosvenor House, 11 St Pauls Square", s: "Birmingham B3 1RB" },
            ].map(c => (
              <div key={c.t} style={{ display: "flex", gap: 14, padding: "18px 0", borderBottom: "1px solid var(--border)" }}>
                <span style={{ width: 46, height: 46, flex: "none", borderRadius: "var(--r-sm)", background: "var(--hivis-50)", color: "var(--accent-ink)", display: "flex", alignItems: "center", justifyContent: "center" }}><Icon name={c.icon} /></span>
                <div>
                  <div style={{ fontFamily: "var(--font-mono)", fontSize: 11, letterSpacing: ".1em", textTransform: "uppercase", color: "var(--mist-400)" }}>{c.t}</div>
                  <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, color: "var(--slate-900)", marginTop: 4 }}>{c.v}</div>
                  <div style={{ fontSize: 13, color: "var(--fg-2)", marginTop: 2 }}>{c.s}</div>
                </div>
              </div>
            ))}
            <div className="media" style={{ marginTop: 22, aspectRatio: "4/3", display: "flex", alignItems: "center", justifyContent: "center", background: "var(--slate-800)" }}>
              <span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--fg-on-dark-2)", letterSpacing: ".1em" }}>[ MAP — St Pauls Square ]</span>
            </div>
          </div>

          {/* form */}
          <div className="form-card">
            {sent ? (
              <div style={{ textAlign: "center", padding: "30px 0" }}>
                <div style={{ width: 60, height: 60, borderRadius: "var(--r)", background: "var(--hivis-50)", color: "var(--accent-ink)", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 16px" }}><Icon name="check" style={{ width: 28 }} /></div>
                <h3 className="h3">Thanks, message sent</h3>
                <p className="lead" style={{ marginTop: 8 }}>We'll be back in touch within one working day.</p>
              </div>
            ) : (
              <form onSubmit={e => { e.preventDefault(); setSent(true); }}>
                <div className="field">
                  <label>What's this about?</label>
                  <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                    {routes.map(r => (
                      <button type="button" key={r.id} onClick={() => setRoute(r.id)}
                        style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "10px 14px", borderRadius: "var(--r-sm)", cursor: "pointer", fontFamily: "var(--font-display)", fontWeight: 600, fontSize: 13.5,
                          border: "1.5px solid " + (route === r.id ? "var(--slate-900)" : "var(--border-strong)"), background: route === r.id ? "var(--slate-900)" : "#fff", color: route === r.id ? "#fff" : "var(--slate-800)" }}>
                        <Icon name={r.icon} /> {r.label}
                      </button>
                    ))}
                  </div>
                </div>
                <div className="row2">
                  <div className="field"><label>Name <span className="req">*</span></label><input className="input" required placeholder="Jane Smith" /></div>
                  <div className="field"><label>{route === "employer" ? "Company" : "Phone"} <span className="req">*</span></label><input className="input" required placeholder={route === "employer" ? "Acme Ltd" : "07700 900000"} /></div>
                </div>
                <div className="field"><label>Email <span className="req">*</span></label><input className="input" type="email" required placeholder="jane@email.com" /></div>
                <div className="field"><label>{route === "employer" ? "What are you hiring for?" : route === "candidate" ? "What kind of work are you after?" : "Your message"}</label><textarea className="input" placeholder="Tell us a bit more…"></textarea></div>
                <label className="consent" style={{ marginBottom: 22 }}><input type="checkbox" required /><span>I consent to Poolhall handling my enquiry per the <a href="#" onClick={e => e.preventDefault()}>Privacy Policy</a>. <span className="req" style={{ color: "var(--accent-ink)" }}>*</span></span></label>
                <Button variant="primary" size="lg" className="btn-block" iconRight="send" type="submit">Send {route === "employer" ? "enquiry" : "message"}</Button>
              </form>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}

window.Contact = Contact;
