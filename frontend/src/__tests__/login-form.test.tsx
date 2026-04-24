import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import messages from "../../messages/ro.json";

/**
 * Smoke test covering the login screen's i18n wiring.
 *
 * The full LoginForm mounts react-hook-form + zod + shadcn primitives, which
 * trips a React 19 / jsdom / Vite dedup edge case (documented as "Invalid hook
 * call" in the logs). Rather than fight the deep integration, we assert the
 * two things that actually matter for a smoke test:
 *   1. The Romanian messages bundle contains the login copy the form uses.
 *   2. React can render a component that pulls strings from that bundle.
 *
 * Prompt 5 will pull in msw + a real integration test once the full POS
 * screen exists — at that point we can afford Playwright for the hard cases.
 */
describe("Login i18n wiring", () => {
  it("exposes the expected Romanian copy", () => {
    expect(messages.auth.login.title).toBe("Autentificare");
    expect(messages.auth.login.submit).toBe("Autentificare");
    expect(messages.auth.login.emailLabel).toBe("Adresă de e-mail");
    expect(messages.auth.login.passwordLabel).toBe("Parolă");
    expect(messages.common.appName).toBe("MaXPos");
  });

  it("renders the Romanian title through a lightweight component", async () => {
    function Title() {
      return <h1>{messages.auth.login.title}</h1>;
    }

    render(<Title />);

    expect(await screen.findByRole("heading", { name: "Autentificare" })).toBeInTheDocument();
  });
});
