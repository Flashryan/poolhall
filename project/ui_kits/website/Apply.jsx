/* Poolhall UI kit — Apply form (push to Giig) + confirmation */

function Apply({ go, job }) {
  const D = window.PH_DATA;
  const j = job || D.jobs[0];
  const F = window.PH_FMT;
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [file, setFile] = useState(null);
  const submit = e => { e.preventDefault(); setBusy(true); setTimeout(() => { setBusy(false); setSent(true); }, 1200); };

  if (sent) {
    return (
      <main className="bg-paper" style={{ minHeight: "70vh", display: "flex", alignItems: "center" }}>
        <div className="container">
          <div className="form-card" style={{ maxWidth: 620, margin: "60px auto", textAlign: "center" }}>
            <div style={{ width: 64, height: 64, borderRadius: "50%", background: "var(--success-50)", color: "var(--success-600)", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 20px" }}>
              <Icon name="check" style={{ width: 30, height: 30 }} />
            </div>
            <h1 className="h2">Application sent</h1>
            <p className="lead" style={{ margin: "12px auto 0", maxWidth: "44ch" }}>Thanks! Your application for <strong>{j.title}</strong> is on its way to our team. We'll be in touch within 2 working days.</p>
            <div style={{ display: "flex", gap: 12, justifyContent: "center", marginTop: 28 }}>
              <Button variant="primary" onClick={() => go("jobs")}>Browse more jobs</Button>
              <Button variant="ghost" onClick={() => go("home")}>Back to home</Button>
            </div>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="bg-paper">
      <div className="container">
        <div className="crumb">
          <a href="#" onClick={e => { e.preventDefault(); go("home"); }}>Home</a>
          <Icon name="chevron-right" />
          <a href="#" onClick={e => { e.preventDefault(); go("job", j); }}>{j.title}</a>
          <Icon name="chevron-right" /> <span>Apply</span>
        </div>
      </div>
      <div className="container" style={{ padding: "20px 24px 80px" }}>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 320px", gap: 40, alignItems: "start" }}>
          <div>
            <p className="eyebrow">Apply now</p>
            <h1 className="h2">Apply for {j.title}</h1>
            <p className="lead" style={{ marginTop: 12, marginBottom: 26 }}>It takes two minutes. Fields marked <span style={{ color: "var(--orange-600)" }}>*</span> are required.</p>
            <form className="form-card" onSubmit={submit}>
              <div className="form-row">
                <div className="fieldset"><label>First name <span className="req">*</span></label><input className="input" required placeholder="Jane" /></div>
                <div className="fieldset"><label>Last name <span className="req">*</span></label><input className="input" required placeholder="Smith" /></div>
              </div>
              <div className="form-row">
                <div className="fieldset"><label>Email <span className="req">*</span></label><input className="input" type="email" required placeholder="jane@email.com" /></div>
                <div className="fieldset"><label>Phone <span className="req">*</span></label><input className="input" required placeholder="07700 900000" /></div>
              </div>
              <div className="fieldset">
                <label>Upload CV <span className="req">*</span></label>
                <label className="dropzone">
                  <Icon name="upload" />
                  <div style={{ fontWeight: 700, color: "var(--fg-1)" }}>{file || "Drag & drop or browse"}</div>
                  <div style={{ fontSize: 12.5, marginTop: 4 }}>PDF or Word · max 5 MB</div>
                  <input type="file" accept=".pdf,.doc,.docx" style={{ display: "none" }} onChange={e => setFile(e.target.files[0]?.name)} />
                </label>
              </div>
              <div className="fieldset"><label>Message <span style={{ color: "var(--fg-3)", fontWeight: 500 }}>(optional)</span></label><textarea className="input" placeholder="A short note about why you're a great fit"></textarea></div>
              <label className="consent" style={{ marginBottom: 22 }}>
                <input type="checkbox" required />
                <span>I consent to Poolhall Recruitment storing and processing my data to handle this application, in line with the <a href="#" style={{ color: "var(--link)", fontWeight: 600 }}>Privacy Policy</a>. <span className="req" style={{ color: "var(--orange-600)" }}>*</span></span>
              </label>
              <Button variant="primary" size="lg" className="btn-block" iconRight="send" busy={busy} type="submit">{busy ? "Submitting…" : "Submit application"}</Button>
              <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 7, marginTop: 14, fontSize: 12.5, color: "var(--fg-3)" }}>
                <Icon name="lock" style={{ width: 14, height: 14 }} /> Secure & GDPR-compliant · protected by reCAPTCHA
              </div>
            </form>
          </div>

          {/* job summary aside */}
          <aside className="apply-aside" style={{ position: "sticky", top: 100 }}>
            <div className="jc-sector">{j.sector}</div>
            <h3 className="jc-title" style={{ marginTop: 6 }}>{j.title}</h3>
            <div className="aside-meta" style={{ marginTop: 16 }}>
              <div className="r"><span>Salary</span><span>{F.salary(j)}</span></div>
              <div className="r"><span>Location</span><span>{j.location}</span></div>
              <div className="r"><span>Type</span><span>{j.type}</span></div>
              <div className="r"><span>Reference</span><span>#{j.id}</span></div>
            </div>
            <a href="#" className="jc-link" onClick={e => { e.preventDefault(); go("job", j); }}>View full description <Icon name="arrow-right" /></a>
          </aside>
        </div>
      </div>
    </main>
  );
}

window.Apply = Apply;
