import { render, screen } from "@testing-library/react";
import { Button } from "@/components/ui/button";

test("renders button with text", () => {
  render(<Button>Click me</Button>);
  expect(screen.getByRole("button", { name: /click me/i })).toBeInTheDocument();
});

test("renders button with variant", () => {
  render(<Button variant="destructive">Delete</Button>);
  const button = screen.getByRole("button", { name: /delete/i });
  expect(button).toBeInTheDocument();
});
