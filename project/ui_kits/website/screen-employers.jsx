/* Poolhall UI kit v2 — Employers hub (§9.2) */

function Employers({ go }) {
  const D = window.PH_DATA;
  const services = [
    { n: "01", icon: "users", t: "Temporary", d: "Flexible workforce when you need to scale up fast, fully compliant and ready to go." },
    { n: "02", icon: "user-check", t: "Permanent", d: "End-to-end search for permanent hires, sourced, screened and shortlisted." },
    { n: "03", icon: "trending-up", t: "Scale", d: "Volume and project recruitment for growth, multiple hires, one partner." },
    { n: "04", icon: "calendar-clock", t: "Pay Monthly", d: "Spread the cost of a permanent hire across manageable monthly payments." },
    { n: "05", icon: "hard-hat", t: "On-Site", d: "A managed on-site service for high-volume, single-location requirements." },
  ];
  return (
    <main>
      <div className="pagehead photo">
        <img src={window.PH_IMG.employersHero} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">For employers</Label>
          <h1 style={{ maxWidth: "16ch" }}>More than just recruitment.</h1>
          <p className="lead">A hiring partner that represents your business like it's our own. Better people, less wasted spend, and a process that respects your time.</p>
          <div style={{ display: "flex", gap: 12, marginTop: 26, flexWrap: "wrap" }}>
            <Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => go("contact")}>Hire talent</Button>
            <Button variant="ghost-light" size="lg" icon="phone">0121 516 3000</Button>
          </div>
        </div>
      </div>

      {/* how Poolhall helps */}
      <section className="section">
        <div className="container">
          <SectionHead idx="01 /" label="How we help employers" title="Hire better, waste less, think long-term"
            lead="Three things our clients tell us make the difference." />
          <div className="points">
            <div className="point"><div className="pi">// 01</div><h3>Hire better people</h3><p>Specialist consultants who understand the role and the sector, so the shortlist is genuinely worth your time.</p></div>
            <div className="point"><div className="pi">// 02</div><h3>Better value for your budget</h3><p>We take the time to get the fit right, so you spend less time on CVs that aren't quite there, and keep your cost-per-hire sensible.</p></div>
            <div className="point"><div className="pi">// 03</div><h3>A strategic partner</h3><p>From a single hire to a whole team, we plan with you, not just react to a vacancy.</p></div>
            <div className="point"><div className="pi">// 04</div><h3>Honest, friendly advice</h3><p>We'll always give you our genuine view on salary, market and timelines, gently and openly, so you can plan with confidence.</p></div>
          </div>
        </div>
      </section>

      {/* delivery options */}
      <section className="section bg-alt">
        <div className="container">
          <SectionHead idx="02 /" label="Recruitment delivery options" title="Five ways to work with us"
            lead="Whatever the shape of your hiring need, there's a model that fits."
            action={<Button variant="ghost" iconRight="arrow-right" onClick={() => go("delivery")}>See all options</Button>} />
          <div className="svc-grid">
            {services.slice(0, 3).map((s, i) => (
              <div className={"svc" + (i === 1 ? " dark" : "")} key={s.t}>
                <div className="svc-photo"><img src={window.PH_SVCIMG(i)} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="num">{s.n}</div>
                <span className="si"><Icon name={s.icon} /></span>
                <h3>{s.t}</h3><p>{s.d}</p>
                <button className="btn-text" style={i === 1 ? { color: "var(--hivis-400)" } : {}} onClick={() => go("delivery")}>Learn more <Icon name="arrow-right" /></button>
              </div>
            ))}
          </div>
          <div className="svc-grid" style={{ gridTemplateColumns: "1fr 1fr", marginTop: 22 }}>
            {services.slice(3).map((s, i) => (
              <div className="svc" key={s.t}>
                <div className="svc-photo"><img src={window.PH_SVCIMG(i + 3)} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
                <div className="num">{s.n}</div>
                <span className="si"><Icon name={s.icon} /></span>
                <h3>{s.t}</h3><p>{s.d}</p>
                <button className="btn-text" onClick={() => go("delivery")}>Learn more <Icon name="arrow-right" /></button>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* service tiers — Bronze / Silver / Gold */}
      <section className="section">
        <div className="container">
          <SectionHead idx="03 /" label="Service levels" title="Three tiers, one standard of care"
            lead="Pick the level of support that fits the role. Every tier is delivered by the same senior consultants." />
          <div className="pricing">
            {/* Bronze */}
            <div className="tier bronze">
              <div className="tier-head">
                <span className="tier-badge"><span className="dot"></span> Bronze</span>
                <h3>Advertise</h3>
                <p className="tier-sub">Get your role seen and a screened shortlist back. Ideal when you'll run interviews in-house.</p>
                <div className="tier-price"><span className="amt">12%</span><span className="per">of first-year salary</span></div>
              </div>
              <ul className="tier-feats">
                <li><Icon name="check" className="yes" /> Branded advert, written for you</li>
                <li><Icon name="check" className="yes" /> Posted to major job boards</li>
                <li><Icon name="check" className="yes" /> CV screening &amp; sift</li>
                <li><Icon name="check" className="yes" /> Shortlist of up to 5</li>
                <li><Icon name="check" className="yes" /> 30-day replacement rebate</li>
                <li className="off"><Icon name="x" className="no" /> Phone pre-screening</li>
                <li className="off"><Icon name="x" className="no" /> Proactive headhunting</li>
                <li className="off"><Icon name="x" className="no" /> Dedicated manager</li>
              </ul>
              <div className="tier-cta"><Button variant="ghost" className="btn-block" onClick={() => go("contact")}>Enquire</Button></div>
            </div>

            {/* Silver */}
            <div className="tier silver">
              <div className="tier-head">
                <span className="tier-badge"><span className="dot"></span> Silver</span>
                <h3>Recruit</h3>
                <p className="tier-sub">Our core managed service — we source, screen and shortlist, you choose and hire.</p>
                <div className="tier-price"><span className="amt">15%</span><span className="per">of first-year salary</span></div>
              </div>
              <ul className="tier-feats">
                <li className="head">Everything in Bronze, plus:</li>
                <li><Icon name="check" className="yes" /> Phone &amp; competency screening</li>
                <li><Icon name="check" className="yes" /> Database &amp; network search</li>
                <li><Icon name="check" className="yes" /> Interview scheduling managed</li>
                <li><Icon name="check" className="yes" /> Structured shortlist with notes</li>
                <li><Icon name="check" className="yes" /> 60-day replacement rebate</li>
                <li className="off"><Icon name="x" className="no" /> Proactive headhunting</li>
                <li className="off"><Icon name="x" className="no" /> Onboarding &amp; aftercare</li>
              </ul>
              <div className="tier-cta"><Button variant="steel" className="btn-block" onClick={() => go("contact")}>Enquire</Button></div>
            </div>

            {/* Gold */}
            <div className="tier gold feat">
              <div className="tier-head">
                <span className="tier-badge"><span className="dot"></span> Gold</span>
                <h3>Search</h3>
                <p className="tier-sub">Full retained search for senior, niche or business-critical hires. We own the whole process.</p>
                <div className="tier-price"><span className="amt">20%</span><span className="per">of first-year salary</span></div>
              </div>
              <ul className="tier-feats">
                <li className="head">Everything in Silver, plus:</li>
                <li><Icon name="check" className="yes" /> Proactive, targeted headhunting</li>
                <li><Icon name="check" className="yes" /> Reference &amp; right-to-work checks</li>
                <li><Icon name="check" className="yes" /> Dedicated account manager</li>
                <li><Icon name="check" className="yes" /> Market &amp; salary insight report</li>
                <li><Icon name="check" className="yes" /> Onboarding &amp; aftercare support</li>
                <li><Icon name="check" className="yes" /> 90-day replacement guarantee</li>
                <li><Icon name="check" className="yes" /> Priority same-day response</li>
              </ul>
              <div className="tier-cta"><Button variant="primary" className="btn-block" onClick={() => go("contact")}>Enquire</Button></div>
            </div>
          </div>
          <p className="pricing-note">Percentages are indicative and based on the placed candidate's first-year salary. Your exact terms, rebates and any pay-monthly options are confirmed in writing when you enquire.</p>
        </div>
      </section>

      {/* links into services + proof */}
      <section className="section">
        <div className="container">
          <div className="split rev">
            <div className="media-collage">
              <div className="m tall"><img src={window.PH_IMG.aboutB} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
              <div className="m"><img src={window.PH_IMG.aboutC} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
              <div className="m"><img src={window.PH_IMG.aboutA} alt="" onError={e => e.target.parentElement.style.background='var(--slate-700)'} /></div>
            </div>
            <div>
              <Label idx="04 /">Specialist services</Label>
              <h2 className="h2">The right tool for the job.</h2>
              <p className="lead" style={{ marginTop: 14 }}>Beyond placement, we offer focused services for specific hiring challenges.</p>
              <ul className="checklist">
                <li><Icon name="badge-check" /> <strong>Why Us</strong> — what makes an independent agency different.</li>
                <li><Icon name="search-check" /> <strong>Bespoke Search</strong> — discreet, targeted headhunting for key roles.</li>
                <li><Icon name="megaphone" /> <strong>Better Job Adverts</strong> — fixed-fee advertising that actually performs.</li>
                <li><Icon name="briefcase" /> <strong>HR Services</strong> — support beyond the hire.</li>
              </ul>
              <div style={{ display: "flex", gap: 12, marginTop: 24, flexWrap: "wrap" }}>
                <Button variant="dark" onClick={() => go("bja")}>Better Job Adverts</Button>
                <Button variant="ghost" onClick={() => go("why-us")}>Why choose us</Button>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* proof strip */}
      <section className="section tight bg-dark">
        <div className="container">
          <SectionHead dark idx="05 /" label="What clients say" title="Trusted to get it right" />
          <Carousel light>{D.reviews.map((r, i) => <ReviewCard key={i} r={r} glass />)}</Carousel>
        </div>
      </section>

      <section className="cta-photo">
        <img src={window.PH_IMG.ctaPhoto} alt="" onError={e => e.target.style.display='none'} />
        <div className="inner">
          <h2>Need to hire? Let's talk.</h2>
          <p>Tell us who you're looking for and we'll come back within one working day. No obligation, no hard sell.</p>
          <div className="acts">
            <Button variant="primary" size="lg" iconRight="arrow-right" onClick={() => go("contact")}>Send an enquiry</Button>
            <Button variant="ghost-light" size="lg" icon="phone" onClick={() => go("contact")}>Book a call</Button>
          </div>
        </div>
      </section>
    </main>
  );
}

window.Employers = Employers;
